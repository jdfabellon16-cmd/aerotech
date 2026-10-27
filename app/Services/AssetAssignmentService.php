<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetRequest;
use App\Models\User;
use App\Notifications\AssetOperationsNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssetAssignmentService
{
    public function assign(AssetRequest $assetRequest, User $technician, int $assetId, string $handoffMethod, ?string $evidencePath, string $policyNotes): AssetAssignment
    {
        if (! $technician->can('assets.manage')) {
            throw new AuthorizationException;
        }

        if ($handoffMethod === 'courier' && $evidencePath === null) {
            throw ValidationException::withMessages([
                'evidence' => 'Courier handoffs require delivery evidence.',
            ]);
        }

        $assignment = DB::transaction(function () use ($assetRequest, $technician, $assetId, $handoffMethod, $evidencePath, $policyNotes): AssetAssignment {
            $assetRequest = AssetRequest::query()
                ->whereKey($assetRequest->id)
                ->lockForUpdate()
                ->firstOrFail();
            $asset = Asset::query()->whereKey($assetId)->lockForUpdate()->firstOrFail();

            if ($assetRequest->status !== 'open' || $assetRequest->assignment()->exists()) {
                throw ValidationException::withMessages(['asset_request' => 'This request has already been fulfilled or is no longer open.']);
            }
            if ($asset->status !== 'available' || $asset->assignee_id !== null) {
                throw ValidationException::withMessages(['asset_id' => 'The selected asset is not available for assignment.']);
            }
            if ($asset->asset_category_id !== $assetRequest->asset_category_id) {
                throw ValidationException::withMessages(['asset_id' => 'The asset category does not match the request.']);
            }
            if ($assetRequest->requested_by_id === $technician->id) {
                throw ValidationException::withMessages(['assigned_to' => 'A technician cannot self-assign a request.']);
            }

            $requester = $assetRequest->requester()->firstOrFail();
            $assetRequest->update([
                'status' => 'assigned_pending_acknowledgement',
                'policy_checked' => true,
                'policy_checked_by_id' => $technician->id,
                'policy_notes' => $policyNotes,
                'assigned_asset_id' => $asset->id,
            ]);
            $asset->update([
                'assignee_id' => $requester->id,
                'assigned_department_id' => $requester->department_id,
                'status' => 'in_use',
            ]);

            return AssetAssignment::create([
                'asset_request_id' => $assetRequest->id,
                'asset_id' => $asset->id,
                'assigned_to_id' => $requester->id,
                'assigned_by_id' => $technician->id,
                'department_id' => $requester->department_id,
                'handoff_method' => $handoffMethod,
                'evidence_path' => $evidencePath,
                'status' => 'pending_acknowledgement',
                'assigned_at' => now(),
            ])->load(['asset', 'assignee', 'assigner', 'request']);
        });
        $assignment->assignee->notify((new AssetOperationsNotification(
            'Asset assigned',
            "Asset {$assignment->asset->asset_tag} has been assigned to you. Acknowledge the accountability form to complete check-out.",
            "/app/assignments",
        ))->afterCommit());

        return $assignment;
    }

    public function acknowledge(AssetAssignment $assignment, User $recipient, string $signatureName): AssetAssignment
    {
        $assignment = DB::transaction(function () use ($assignment, $recipient, $signatureName): AssetAssignment {
            $assignment = AssetAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();

            if ($assignment->assigned_to_id !== $recipient->id) {
                throw new AuthorizationException('Only the assigned employee may acknowledge this form.');
            }
            if ($assignment->status !== 'pending_acknowledgement') {
                throw ValidationException::withMessages(['status' => 'This assignment is no longer awaiting acknowledgement.']);
            }

            $assignment->update([
                'signature_name' => $signatureName,
                'acknowledged_by_id' => $recipient->id,
                'acknowledged_at' => now(),
                'status' => 'active',
            ]);
            $assignment->request()->update(['status' => 'fulfilled']);

            return $assignment->fresh(['asset', 'assignee', 'assigner', 'request']);
        });
        $assignment->assigner->notify((new AssetOperationsNotification(
            'Asset check-out acknowledged',
            "{$recipient->name} acknowledged asset {$assignment->asset->asset_tag}.",
            '/app/assignments',
        ))->afterCommit());

        return $assignment;
    }

    public function checkIn(AssetAssignment $assignment, User $user): AssetAssignment
    {
        $assignment = DB::transaction(function () use ($assignment, $user): AssetAssignment {
            $assignment = AssetAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            if (
                $assignment->status !== 'active'
                || ($assignment->assigned_to_id !== $user->id && ! $user->can('assets.manage'))
            ) {
                throw new AuthorizationException('Only the assignee or an asset technician may check in this asset.');
            }

            $asset = Asset::query()->whereKey($assignment->asset_id)->lockForUpdate()->firstOrFail();
            $asset->update([
                'assignee_id' => null,
                'assigned_department_id' => null,
                'status' => 'available',
            ]);
            $assignment->update(['status' => 'returned', 'returned_at' => now()]);

            return $assignment->fresh(['asset', 'assignee', 'assigner']);
        });

        $assignment->assigner->notify((new AssetOperationsNotification(
            'Asset checked in',
            "Asset {$assignment->asset->asset_tag} was checked in and is available.",
            '/app/assets',
        ))->afterCommit());

        return $assignment;
    }
}
