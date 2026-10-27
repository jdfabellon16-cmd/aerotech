<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\LifecycleReview;
use App\Models\LifecycleRule;
use App\Models\MaintenanceTicket;
use Illuminate\Support\Collection;

class LifecycleReviewService
{
    /**
     * @return Collection<int, LifecycleReview>
     */
    public function flagDueAssets(): Collection
    {
        $reviews = collect();

        LifecycleRule::query()
            ->where('active', true)
            ->with('category')
            ->chunkById(100, function (Collection $rules) use ($reviews): void {
                foreach ($rules as $rule) {
                    Asset::query()
                        ->where('asset_category_id', $rule->asset_category_id)
                        ->whereNotIn('status', ['retired', 'disposed', 'lost_stolen'])
                        ->whereNotNull('purchase_date')
                        ->chunkById(200, function (Collection $assets) use ($rule, $reviews): void {
                            foreach ($assets as $asset) {
                                $ageDue = $asset->purchase_date->copy()
                                    ->addYears($rule->retirement_age_years)
                                    ->lessThanOrEqualTo(today());
                                $incidentCount = MaintenanceTicket::query()
                                    ->where('asset_id', $asset->id)
                                    ->where('created_at', '>=', now()->subMonths($rule->incident_window_months))
                                    ->count();

                                if (! $ageDue && $incidentCount < $rule->incident_threshold) {
                                    continue;
                                }

                                $reviews->push(LifecycleReview::updateOrCreate(
                                    ['asset_id' => $asset->id, 'review_date' => today()],
                                    ['incident_count' => $incidentCount, 'status' => 'due'],
                                ));
                            }
                        });
                }
            });

        return $reviews;
    }
}
