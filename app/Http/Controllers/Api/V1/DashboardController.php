<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetTransfer;
use App\Models\AssetCategory;
use App\Models\LicenseAssignment;
use App\Models\LicenseComplianceCheck;
use App\Models\LifecycleReview;
use App\Models\Repair;
use App\Models\SoftwareLicense;
use App\Models\PurchaseRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $canViewAssets = $user->can('assets.view');
        $requestQuery = PurchaseRequest::query();

        if (! $user->can('purchase_requests.view_all')) {
            $requestQuery->where('requested_by_id', $user->id);
        }

        $assetSummary = $canViewAssets
            ? Asset::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
            : collect();

        return response()->json([
            'data' => [
                'assets_by_status' => $assetSummary,
                'asset_total' => $canViewAssets ? Asset::count() : null,
                'categories' => $canViewAssets
                    ? AssetCategory::query()->withCount('assets')->orderBy('name')->get(['id', 'name'])
                    : [],
                'purchase_requests' => [
                    'total' => (clone $requestQuery)->count(),
                    'awaiting_action' => (clone $requestQuery)->whereIn('status', [
                        'pending_department_approval',
                        'pending_finance_approval',
                        'pending_general_approval',
                    ])->count(),
                    'rejected' => (clone $requestQuery)->where('status', 'rejected')->count(),
                ],
                'operations' => [
                    'warranty_expiring_soon' => $canViewAssets
                        ? Asset::query()->whereBetween('warranty_expires_at', [today(), today()->addDays(30)])->count()
                        : null,
                    'overdue_loans' => $user->can('transfers.manage') || $user->can('transfers.create')
                        ? AssetTransfer::query()->where('transfer_type', 'temporary')->where('status', 'active')->whereDate('due_at', '<', today())->count()
                        : null,
                    'unsigned_forms' => $user->can('assets.manage')
                        ? AssetAssignment::query()->where('status', 'pending_acknowledgement')->count()
                        : null,
                    'license_seats' => $user->can('licenses.manage') || $user->can('licenses.assign')
                        ? [
                            'purchased' => SoftwareLicense::sum('total_seats'),
                            'assigned' => LicenseAssignment::whereNull('revoked_at')->count(),
                        ]
                        : null,
                    'latest_license_compliance' => $user->can('licenses.manage')
                        ? LicenseComplianceCheck::query()->latest('checked_at')->value('status')
                        : null,
                    'lifecycle_reviews_due' => $user->can('lifecycle.manage')
                        ? LifecycleReview::query()->where('status', 'due')->count()
                        : null,
                    'repair_cost_total' => $user->can('reports.view') ? Repair::sum('cost') : null,
                    'warranty_repairs' => $user->can('reports.view') ? Repair::where('warranty_used', true)->count() : null,
                ],
            ],
        ]);
    }
}
