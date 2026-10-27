<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\MaintenanceTicket;
use App\Models\Repair;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MaintenanceService
{
    public function __construct(private WarrantyService $warrantyService) {}

    public function report(User $reporter, int $assetId, string $description, string $priority): MaintenanceTicket
    {
        if (! $reporter->can('maintenance.create') && ! $reporter->can('maintenance.manage')) {
            throw new AuthorizationException;
        }

        $asset = Asset::query()->findOrFail($assetId);
        if (
            ! $reporter->can('maintenance.manage')
            && $asset->assignee_id !== $reporter->id
        ) {
            throw new AuthorizationException('You may only report an incident for an asset assigned to you.');
        }

        $slaHours = match ($priority) {
            'critical' => 4,
            'high' => 8,
            'low' => 72,
            default => 24,
        };
        $warrantyCovered = $this->warrantyService->isCovered($asset);

        $ticket = MaintenanceTicket::create([
            'ticket_number' => 'MT-'.Str::upper(Str::random(10)),
            'asset_id' => $asset->id,
            'reported_by_id' => $reporter->id,
            'issue_description' => $description,
            'assessment_checklist' => [
                'power_and_boot' => false,
                'physical_damage' => false,
                'connectivity' => false,
                'security_and_data' => false,
                'troubleshooting_attempted' => false,
            ],
            'priority' => $priority,
            'sla_due_at' => now()->addHours($slaHours),
            'status' => 'open',
            'warranty_covered' => $warrantyCovered,
            'warranty_checked_at' => now(),
            'opened_at' => now(),
        ]);
        $asset->update(['status' => 'in_repair']);

        return $ticket->load(['asset', 'reporter']);
    }

    public function updateChecklist(MaintenanceTicket $ticket, User $technician, array $checklist): MaintenanceTicket
    {
        if (! $technician->can('maintenance.manage')) {
            throw new AuthorizationException;
        }

        $ticket->update(['assessment_checklist' => $checklist, 'assigned_to_id' => $technician->id]);

        return $ticket->fresh(['asset', 'reporter']);
    }

    public function recordRepair(MaintenanceTicket $ticket, User $technician, array $data): Repair
    {
        if (! $technician->can('maintenance.manage')) {
            throw new AuthorizationException;
        }
        if ($ticket->status === 'closed') {
            throw ValidationException::withMessages(['ticket' => 'A closed incident cannot receive another repair record.']);
        }

        $paid = (bool) ($data['paid'] ?? false);
        $cost = (float) ($data['cost'] ?? 0);
        $repair = Repair::create([
            'maintenance_ticket_id' => $ticket->id,
            'asset_id' => $ticket->asset_id,
            'performed_by_id' => $technician->id,
            'started_at' => now(),
            'cost' => $cost,
            'paid' => $paid,
            'cost_approved' => ! $paid || $cost === 0.0,
            'hr_cost_deduction_flag' => (bool) ($data['hr_cost_deduction_flag'] ?? false),
            'warranty_used' => $ticket->warranty_covered,
            'repair_summary' => $data['repair_summary'] ?? null,
        ]);
        $ticket->update(['status' => $repair->cost_approved ? 'repair_in_progress' : 'pending_cost_approval']);

        return $repair;
    }

    public function approveRepairCost(Repair $repair, User $approver, bool $hrCostDeductionFlag): Repair
    {
        if (! $approver->can('repairs.approve')) {
            throw new AuthorizationException;
        }
        if (! $repair->paid || $repair->cost <= 0 || $repair->cost_approved) {
            throw ValidationException::withMessages(['repair' => 'This repair does not require a paid-cost approval.']);
        }

        $repair->update([
            'cost_approved' => true,
            'cost_approved_by_id' => $approver->id,
            'hr_cost_deduction_flag' => $hrCostDeductionFlag,
        ]);
        $repair->ticket()->update(['status' => 'repair_in_progress']);

        return $repair->fresh();
    }

    public function completeRepair(Repair $repair, User $technician): Repair
    {
        if (! $technician->can('maintenance.manage')) {
            throw new AuthorizationException;
        }
        if ($repair->paid && $repair->cost > 0 && ! $repair->cost_approved) {
            throw ValidationException::withMessages(['repair' => 'Paid repair costs must be approved before closure.']);
        }

        return DB::transaction(function () use ($repair): Repair {
            $repair->update(['completed_at' => now()]);
            $repair->ticket()->update(['status' => 'closed', 'closed_at' => now()]);
            $asset = $repair->asset()->lockForUpdate()->firstOrFail();
            $asset->update(['status' => $asset->assignee_id === null ? 'available' : 'in_use']);

            return $repair->fresh(['asset', 'ticket']);
        });
    }
}
