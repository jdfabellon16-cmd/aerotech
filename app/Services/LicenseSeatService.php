<?php

namespace App\Services;

use App\Models\LicenseAssignment;
use App\Models\LicenseComplianceCheck;
use App\Models\SoftwareLicense;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LicenseSeatService
{
    public function assign(SoftwareLicense $license, User $assigner, ?int $userId, ?int $assetId): LicenseAssignment
    {
        if (! $assigner->can('licenses.assign')) {
            throw new AuthorizationException;
        }
        if ($userId === null && $assetId === null) {
            throw ValidationException::withMessages(['assignee' => 'Select a user or an asset for this license seat.']);
        }

        return DB::transaction(function () use ($license, $assigner, $userId, $assetId): LicenseAssignment {
            $license = SoftwareLicense::query()->whereKey($license->id)->lockForUpdate()->firstOrFail();
            if ($license->expires_at !== null && $license->expires_at->lessThan(today())) {
                throw ValidationException::withMessages(['license' => 'Expired licenses cannot be assigned.']);
            }

            $activeAssignments = $license->activeAssignments();
            if ($activeAssignments->count() >= $license->total_seats) {
                throw ValidationException::withMessages(['license' => 'All purchased license seats are already assigned.']);
            }

            $duplicate = $activeAssignments->where(function ($query) use ($userId, $assetId): void {
                if ($userId !== null) {
                    $query->orWhere('user_id', $userId);
                }
                if ($assetId !== null) {
                    $query->orWhere('asset_id', $assetId);
                }
            })->exists();

            if ($duplicate) {
                throw ValidationException::withMessages(['assignee' => 'This user or device already has an active seat for this license.']);
            }

            return LicenseAssignment::create([
                'software_license_id' => $license->id,
                'user_id' => $userId,
                'asset_id' => $assetId,
                'assigned_by_id' => $assigner->id,
                'assigned_at' => now(),
            ]);
        });
    }

    public function checkCompliance(?SoftwareLicense $license, ?User $auditor): LicenseComplianceCheck
    {
        if ($auditor !== null && ! $auditor->can('licenses.manage')) {
            throw new AuthorizationException;
        }

        $licenses = $license === null ? SoftwareLicense::query()->get() : collect([$license]);
        $seatsPurchased = $licenses->sum('total_seats');
        $seatsAssigned = $licenses->sum(fn (SoftwareLicense $item): int => $item->activeAssignments()->count());
        $findings = $licenses->map(function (SoftwareLicense $item): array {
            $assigned = $item->activeAssignments()->count();

            return [
                'license_id' => $item->id,
                'name' => $item->name,
                'seats_purchased' => $item->total_seats,
                'seats_assigned' => $assigned,
                'over_assigned' => $assigned > $item->total_seats,
            ];
        })->all();

        return LicenseComplianceCheck::create([
            'software_license_id' => $license?->id,
            'performed_by_id' => $auditor?->id,
            'status' => $seatsAssigned > $seatsPurchased ? 'non_compliant' : 'compliant',
            'seats_purchased' => $seatsPurchased,
            'seats_assigned' => $seatsAssigned,
            'findings' => $findings,
            'checked_at' => now(),
        ]);
    }
}
