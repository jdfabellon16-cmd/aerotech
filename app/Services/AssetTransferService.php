<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetTransfer;
use App\Models\LocationChangeLog;
use App\Models\User;
use App\Notifications\AssetOperationsNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssetTransferService
{
    public function request(User $requester, array $data): AssetTransfer
    {
        if (! $requester->can('transfers.create')) {
            throw new AuthorizationException;
        }

        if ($data['transfer_type'] === 'temporary') {
            $start = Carbon::parse($data['loan_started_at']);
            $due = Carbon::parse($data['due_at']);
            $maxDue = $start->copy()->addMonthNoOverflow()->startOfDay();
            if ($due->lt($start->copy()->startOfDay()) || $due->gt($maxDue)) {
                throw ValidationException::withMessages(['due_at' => 'A loan cannot exceed one month from its start date.']);
            }
            if (! empty($data['replacement_expected_at']) && $due->gt($data['replacement_expected_at'])) {
                throw ValidationException::withMessages(['due_at' => 'The loan due date cannot be later than the replacement arrival date.']);
            }
        } elseif (! empty($data['due_at']) || ! empty($data['loan_started_at'])) {
            throw ValidationException::withMessages(['transfer_type' => 'Permanent transfers do not have loan dates.']);
        }

        $asset = Asset::query()->findOrFail($data['asset_id']);
        if (
            $asset->assignee_id !== $requester->id
            && ! $requester->can('assets.manage')
        ) {
            throw new AuthorizationException('You may only request a transfer for an asset assigned to you.');
        }

        return AssetTransfer::create([
            ...$data,
            'requested_by_id' => $requester->id,
            'from_user_id' => $asset->assignee_id,
            'from_location_id' => $asset->location_id,
            'status' => 'pending_approval',
        ])->load(['asset', 'requester', 'recipient', 'destination']);
    }

    public function approve(AssetTransfer $transfer, User $approver): AssetTransfer
    {
        if (! $approver->can('transfers.manage')) {
            throw new AuthorizationException;
        }

        if ($transfer->status !== 'pending_approval') {
            throw ValidationException::withMessages(['status' => 'This transfer is not awaiting approval.']);
        }

        $transfer->update([
            'status' => 'pending_acceptance',
            'approved_by_id' => $approver->id,
            'loan_started_at' => $transfer->transfer_type === 'temporary' ? ($transfer->loan_started_at ?? now()) : null,
        ]);

        $transfer = $transfer->fresh(['asset', 'requester', 'recipient', 'destination']);
        $transfer->recipient?->notify((new AssetOperationsNotification(
            'Asset transfer approved',
            "A transfer of asset {$transfer->asset->asset_tag} is ready for your acceptance.",
            '/app/transfers',
        ))->afterCommit());

        return $transfer;
    }

    public function accept(AssetTransfer $transfer, User $recipient): AssetTransfer
    {
        return DB::transaction(function () use ($transfer, $recipient): AssetTransfer {
            $transfer = AssetTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            if ($transfer->status !== 'pending_acceptance' || $transfer->to_user_id !== $recipient->id) {
                throw new AuthorizationException('Only the transfer recipient may accept an approved transfer.');
            }

            $asset = Asset::query()->whereKey($transfer->asset_id)->lockForUpdate()->firstOrFail();
            $previousLocationId = $asset->location_id;
            $asset->update([
                'location_id' => $transfer->to_location_id,
                'assignee_id' => $transfer->to_user_id,
                'assigned_department_id' => $recipient->department_id,
            ]);
            $transfer->update(['status' => 'active', 'accepted_at' => now()]);
            LocationChangeLog::create([
                'asset_id' => $asset->id,
                'transfer_id' => $transfer->id,
                'from_location_id' => $previousLocationId,
                'to_location_id' => $transfer->to_location_id,
                'changed_by_id' => $recipient->id,
                'accepted_by_id' => $recipient->id,
                'accepted_at' => now(),
                'changed_at' => now(),
            ]);

            return $transfer->fresh(['asset', 'requester', 'recipient', 'destination']);
        });
    }
}
