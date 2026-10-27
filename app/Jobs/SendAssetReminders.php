<?php

namespace App\Jobs;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetTransfer;
use App\Models\LicenseRenewalReview;
use App\Models\LifecycleReview;
use App\Models\SoftwareLicense;
use App\Models\User;
use App\Notifications\AssetOperationsNotification;
use App\Services\LifecycleReviewService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendAssetReminders implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public $backoff = [60, 300];

    public function handle(LifecycleReviewService $lifecycleReviewService): void
    {
        $this->notifyUnsignedForms();
        $this->notifyOverdueLoans();
        $this->notifyExpiringWarranties();
        $this->notifyLicenseRenewals();
        $this->notifyLifecycleReviews($lifecycleReviewService);
    }

    private function notifyUnsignedForms(): void
    {
        AssetAssignment::query()
            ->with(['asset', 'assignee'])
            ->where('status', 'pending_acknowledgement')
            ->where('assigned_at', '<=', now()->subDay())
            ->chunkById(100, function ($assignments): void {
                foreach ($assignments as $assignment) {
                    $assignment->assignee->notify(new AssetOperationsNotification(
                        'Asset acknowledgement required',
                        "Please acknowledge receipt of asset {$assignment->asset->asset_tag}.",
                        "/app/assignments/{$assignment->id}",
                    ));
                }
            });
    }

    private function notifyOverdueLoans(): void
    {
        AssetTransfer::query()
            ->with(['asset', 'recipient'])
            ->where('transfer_type', 'temporary')
            ->where('status', 'active')
            ->whereDate('due_at', '<', today())
            ->chunkById(100, function ($transfers): void {
                foreach ($transfers as $transfer) {
                    if ($transfer->recipient !== null) {
                        $transfer->recipient->notify(new AssetOperationsNotification(
                            'Temporary asset loan is overdue',
                            "Return or renew asset {$transfer->asset->asset_tag}; its loan due date has passed.",
                            '/app/transfers',
                        ));
                    }
                }
            });
    }

    private function notifyExpiringWarranties(): void
    {
        foreach ([30, 14, 7] as $days) {
            Asset::query()
                ->with('assignee')
                ->whereDate('warranty_expires_at', today()->addDays($days))
                ->chunkById(100, function ($assets) use ($days): void {
                    foreach ($assets as $asset) {
                        $recipients = $asset->assignee === null
                            ? User::role('IT Asset Manager')->get()
                            : collect([$asset->assignee]);

                        foreach ($recipients as $recipient) {
                            $recipient->notify(new AssetOperationsNotification(
                                'Asset warranty expiring soon',
                                "Warranty for asset {$asset->asset_tag} expires in {$days} days.",
                                '/app/assets',
                            ));
                        }
                    }
                });
        }
    }

    private function notifyLicenseRenewals(): void
    {
        foreach ([90, 60, 30] as $days) {
            SoftwareLicense::query()
                ->whereDate('expires_at', today()->addDays($days))
                ->chunkById(100, function ($licenses) use ($days): void {
                    foreach ($licenses as $license) {
                        $review = LicenseRenewalReview::firstOrCreate([
                            'software_license_id' => $license->id,
                            'expires_at' => $license->expires_at,
                            'days_before_expiry' => $days,
                        ]);

                        if (! $review->wasRecentlyCreated) {
                            continue;
                        }

                        User::role(['IT Asset Manager', 'IT Operations Manager'])
                            ->get()
                            ->each(fn (User $user) => $user->notify(new AssetOperationsNotification(
                                'Software license renewal review',
                                "{$license->name} expires in {$days} days. Complete the IT renewal review.",
                                '/app/licenses',
                            )));
                    }
                });
        }
    }

    private function notifyLifecycleReviews(LifecycleReviewService $lifecycleReviewService): void
    {
        $newReviews = $lifecycleReviewService->flagDueAssets()->filter(
            fn (LifecycleReview $review): bool => $review->wasRecentlyCreated,
        );

        if ($newReviews->isEmpty()) {
            return;
        }

        $managers = User::role('IT Asset Manager')->get();
        foreach ($newReviews as $review) {
            foreach ($managers as $manager) {
                $manager->notify(new AssetOperationsNotification(
                    'Asset due for lifecycle review',
                    "Asset {$review->asset_id} meets the lifecycle review criteria.",
                    '/app/lifecycle',
                ));
            }
        }
    }
}
