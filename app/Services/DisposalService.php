<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Disposal;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DisposalService
{
    public function request(User $requester, int $assetId, string $disposalType): Disposal
    {
        if (! $requester->can('disposals.create')) {
            throw new AuthorizationException;
        }

        $asset = Asset::query()->findOrFail($assetId);
        if ($asset->assignee_id !== $requester->id && ! $requester->can('assets.manage')) {
            throw new AuthorizationException('You may only request disposal review for an asset assigned to you.');
        }
        if (in_array($asset->status, ['retired', 'disposed', 'lost_stolen'], true)) {
            throw ValidationException::withMessages(['asset_id' => 'This asset is not eligible for disposal review.']);
        }

        return Disposal::create([
            'asset_id' => $asset->id,
            'requested_by_id' => $requester->id,
            'disposal_type' => $disposalType,
            'status' => 'pending_finance_approval',
        ]);
    }

    public function approveFinance(Disposal $disposal, User $approver): Disposal
    {
        if (! $approver->can('disposals.approve.finance')) {
            throw new AuthorizationException;
        }
        if ($disposal->status !== 'pending_finance_approval') {
            throw ValidationException::withMessages(['status' => 'This disposal is not awaiting Finance approval.']);
        }

        $disposal->update([
            'status' => 'pending_management_approval',
            'finance_approved_by_id' => $approver->id,
        ]);

        return $disposal->fresh(['asset']);
    }

    public function approveManagement(Disposal $disposal, User $approver): Disposal
    {
        if (! $approver->can('disposals.approve.management')) {
            throw new AuthorizationException;
        }
        if ($disposal->status !== 'pending_management_approval') {
            throw ValidationException::withMessages(['status' => 'This disposal is not awaiting management approval.']);
        }

        $disposal->update([
            'status' => 'pending_completion',
            'management_approved_by_id' => $approver->id,
        ]);

        return $disposal->fresh(['asset']);
    }

    public function complete(Disposal $disposal, User $operator, array $data): Disposal
    {
        if (! $operator->can('disposals.complete')) {
            throw new AuthorizationException;
        }
        if ($disposal->status !== 'pending_completion') {
            throw ValidationException::withMessages(['status' => 'Both Finance and management approval are required first.']);
        }
        if (! $data['data_wipe_done'] || ! $data['company_files_removed']) {
            throw ValidationException::withMessages(['checklist' => 'Confirm both the data wipe and company-file removal before completion.']);
        }
        if (empty($data['certificate_path']) || empty($data['recipient_or_vendor'])) {
            throw ValidationException::withMessages(['certificate' => 'A destruction certificate and recipient/vendor are required.']);
        }

        return DB::transaction(function () use ($disposal, $data): Disposal {
            $asset = Asset::query()->whereKey($disposal->asset_id)->lockForUpdate()->firstOrFail();
            $asset->update(['status' => $disposal->disposal_type === 'retirement' ? 'retired' : 'disposed']);
            $disposal->update([
                ...$data,
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            return $disposal->fresh(['asset']);
        });
    }
}
