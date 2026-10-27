<?php

namespace App\Services;

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseRequestService
{
    public function create(User $requester, array $data): PurchaseRequest
    {
        if ($requester->department_id === null) {
            throw ValidationException::withMessages([
                'department' => 'Your account must have a department before you can submit a purchase request.',
            ]);
        }

        return DB::transaction(function () use ($requester, $data): PurchaseRequest {
            $purchaseRequest = PurchaseRequest::create([
                ...$data,
                'request_number' => 'PR-'.Str::upper(Str::random(10)),
                'requested_by_id' => $requester->id,
                'department_id' => $requester->department_id,
                'status' => 'pending_department_approval',
                'submission_round' => 1,
            ]);

            $purchaseRequest->approvals()->create([
                'submission_round' => 1,
                'stage' => 'department',
            ]);

            return $purchaseRequest->load(['requester', 'department', 'category', 'approvals.approver']);
        });
    }

    public function decide(PurchaseRequest $purchaseRequest, User $approver, string $decision, ?string $reason): PurchaseRequest
    {
        return DB::transaction(function () use ($purchaseRequest, $approver, $decision, $reason): PurchaseRequest {
            $purchaseRequest = PurchaseRequest::query()
                ->whereKey($purchaseRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            $stage = match ($purchaseRequest->status) {
                'pending_department_approval' => 'department',
                'pending_finance_approval' => 'finance',
                'pending_general_approval' => 'general',
                default => throw ValidationException::withMessages([
                    'status' => 'This request is not awaiting an approval decision.',
                ]),
            };

            if (
                ! $approver->can("purchase_requests.review.{$stage}")
                || ($stage === 'department' && $approver->department_id !== $purchaseRequest->department_id)
            ) {
                throw new AuthorizationException('You are not authorized to review this approval stage.');
            }

            $approval = $purchaseRequest->approvals()
                ->where('submission_round', $purchaseRequest->submission_round)
                ->where('stage', $stage)
                ->where('decision', 'pending')
                ->lockForUpdate()
                ->first();

            if ($approval === null) {
                throw ValidationException::withMessages([
                    'status' => 'The active approval step could not be found.',
                ]);
            }

            $approval->update([
                'decision' => $decision,
                'approver_id' => $approver->id,
                'reason' => $decision === 'rejected' ? $reason : null,
                'acted_at' => now(),
            ]);

            if ($decision === 'rejected') {
                $purchaseRequest->update(['status' => 'rejected']);
            } else {
                $nextStage = match ($stage) {
                    'department' => 'finance',
                    'finance' => 'general',
                    default => null,
                };

                if ($nextStage === null) {
                    $purchaseRequest->update(['status' => 'approved']);
                } else {
                    $purchaseRequest->update(['status' => "pending_{$nextStage}_approval"]);
                    $purchaseRequest->approvals()->create([
                        'submission_round' => $purchaseRequest->submission_round,
                        'stage' => $nextStage,
                    ]);
                }
            }

            return $purchaseRequest->load(['requester', 'department', 'category', 'approvals.approver']);
        });
    }

    public function resubmit(PurchaseRequest $purchaseRequest, User $requester, array $data): PurchaseRequest
    {
        return DB::transaction(function () use ($purchaseRequest, $requester, $data): PurchaseRequest {
            $purchaseRequest = PurchaseRequest::query()
                ->whereKey($purchaseRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($purchaseRequest->requested_by_id !== $requester->id) {
                throw new AuthorizationException('Only the original requester may resubmit this request.');
            }

            if ($purchaseRequest->status !== 'rejected') {
                throw ValidationException::withMessages([
                    'status' => 'Only rejected requests may be resubmitted.',
                ]);
            }

            $nextRound = $purchaseRequest->submission_round + 1;
            $purchaseRequest->update([
                ...$data,
                'status' => 'pending_department_approval',
                'submission_round' => $nextRound,
            ]);
            $purchaseRequest->approvals()->create([
                'submission_round' => $nextRound,
                'stage' => 'department',
            ]);

            return $purchaseRequest->load(['requester', 'department', 'category', 'approvals.approver']);
        });
    }
}
