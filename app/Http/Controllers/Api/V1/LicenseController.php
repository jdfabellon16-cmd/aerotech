<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LicenseAssignment;
use App\Models\LicenseComplianceCheck;
use App\Models\LicenseRenewalReview;
use App\Models\LicenseRequest;
use App\Models\SoftwareLicense;
use App\Models\User;
use App\Notifications\AssetOperationsNotification;
use App\Services\LicenseSeatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LicenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('licenses.manage')
                || $request->user()->can('licenses.assign')
                || $request->user()->can('licenses.request'),
            403,
        );

        return response()->json([
            'data' => SoftwareLicense::query()
                ->withCount('activeAssignments')
                ->orderBy('name')
                ->get()
                ->map(fn (SoftwareLicense $license): array => [
                    'id' => $license->id,
                    'name' => $license->name,
                    'vendor' => $license->vendor,
                    'total_seats' => $license->total_seats,
                    'assigned_seats' => $license->active_assignments_count,
                    'cost' => $license->cost,
                    'expires_at' => $license->expires_at?->toDateString(),
                ]),
        ]);
    }

    public function storeRequest(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('licenses.request'), 403);
        $data = $request->validate([
            'software_name' => ['required', 'string', 'max:255'],
            'business_justification' => ['required', 'string', 'min:10', 'max:5000'],
        ]);
        $licenseRequest = LicenseRequest::create([
            ...$data,
            'request_number' => 'LR-'.Str::upper(Str::random(10)),
            'requested_by_id' => $request->user()->id,
            'department_id' => $request->user()->department_id,
            'status' => 'pending_manager_approval',
        ]);

        return response()->json(['data' => $licenseRequest], 201);
    }

    public function requests(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('licenses.request')
                || $request->user()->can('licenses.approve.manager')
                || $request->user()->can('licenses.manage'),
            403,
        );
        $query = LicenseRequest::query()->with(['requester', 'license'])->latest('id');
        if (
            ! $request->user()->can('licenses.approve.manager')
            && ! $request->user()->can('licenses.manage')
        ) {
            $query->where('requested_by_id', $request->user()->id);
        }

        return response()->json($query->paginate(20));
    }

    public function decideRequest(Request $request, LicenseRequest $licenseRequest): JsonResponse
    {
        abort_unless($request->user()->can('licenses.approve.manager'), 403);
        if ($licenseRequest->status !== 'pending_manager_approval') {
            throw ValidationException::withMessages(['status' => 'This request is not awaiting manager approval.']);
        }
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'reason' => ['required_if:decision,rejected', 'nullable', 'string', 'max:2000'],
        ]);
        $licenseRequest->update([
            'status' => $data['decision'] === 'approved' ? 'pending_purchasing' : 'rejected',
            'manager_id' => $request->user()->id,
            'decision_reason' => $data['reason'] ?? null,
        ]);

        return response()->json(['data' => $licenseRequest->fresh()]);
    }

    public function record(Request $request, LicenseRequest $licenseRequest): JsonResponse
    {
        abort_unless($request->user()->can('licenses.manage'), 403);
        $data = $request->validate([
            'vendor' => ['required', 'string', 'max:255'],
            'license_key' => ['nullable', 'string', 'max:2000'],
            'total_seats' => ['required', 'integer', 'min:1', 'max:100000'],
            'cost' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'expires_at' => ['nullable', 'date'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
        ]);

        $license = DB::transaction(function () use ($data, $licenseRequest, $request): SoftwareLicense {
            $licenseRequest = LicenseRequest::query()
                ->whereKey($licenseRequest->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($licenseRequest->status !== 'pending_purchasing') {
                throw ValidationException::withMessages([
                    'status' => 'Purchasing can record a license only after manager approval.',
                ]);
            }

            $license = SoftwareLicense::create([
                ...$data,
                'name' => $licenseRequest->software_name,
                'recorded_by_id' => $request->user()->id,
            ]);
            $licenseRequest->update([
                'status' => 'recorded',
                'purchasing_user_id' => $request->user()->id,
                'software_license_id' => $license->id,
            ]);

            return $license;
        });

        return response()->json(['data' => $license], 201);
    }

    public function assign(
        Request $request,
        SoftwareLicense $softwareLicense,
        LicenseSeatService $service,
    ): JsonResponse {
        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'required_without:asset_id'],
            'asset_id' => ['nullable', 'integer', 'exists:assets,id', 'required_without:user_id'],
        ]);

        return response()->json([
            'data' => $service->assign(
                $softwareLicense,
                $request->user(),
                $data['user_id'] ?? null,
                $data['asset_id'] ?? null,
            ),
        ], 201);
    }

    public function revoke(
        Request $request,
        SoftwareLicense $softwareLicense,
        LicenseAssignment $licenseAssignment,
    ): JsonResponse {
        abort_unless($request->user()->can('licenses.manage'), 403);
        abort_unless($licenseAssignment->software_license_id === $softwareLicense->id, 404);
        if ($licenseAssignment->revoked_at === null) {
            $licenseAssignment->update(['revoked_at' => now()]);
        }

        return response()->json(['message' => 'License assignment revoked.']);
    }

    public function compliance(Request $request, LicenseSeatService $service): JsonResponse
    {
        abort_unless($request->user()->can('licenses.manage'), 403);

        return response()->json(['data' => $service->checkCompliance(null, $request->user())], 201);
    }

    public function complianceHistory(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('licenses.manage'), 403);

        return response()->json([
            'data' => LicenseComplianceCheck::query()
                ->with(['license', 'performedBy'])
                ->latest('checked_at')
                ->paginate(25),
        ]);
    }

    public function renewalReviews(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('licenses.manage') || $request->user()->can('licenses.review.renewals'),
            403,
        );

        return response()->json([
            'data' => LicenseRenewalReview::query()
                ->with('license')
                ->whereIn('status', ['pending_it_review', 'pending_management_review'])
                ->latest('expires_at')
                ->paginate(25),
        ]);
    }

    public function reviewRenewal(Request $request, LicenseRenewalReview $licenseRenewalReview): JsonResponse
    {
        $data = $request->validate(['notes' => ['required', 'string', 'max:3000']]);

        if ($licenseRenewalReview->status === 'pending_it_review') {
            abort_unless($request->user()->can('licenses.manage'), 403);
            $licenseRenewalReview->update([
                'status' => 'pending_management_review',
                'it_reviewed_by_id' => $request->user()->id,
                'it_reviewed_at' => now(),
                'it_notes' => $data['notes'],
            ]);
            User::permission('licenses.review.renewals')
                ->get()
                ->each(fn (User $user) => $user->notify((new AssetOperationsNotification(
                    'Management software renewal review',
                    "IT reviewed {$licenseRenewalReview->license->name}; management review is now due.",
                    '/app/licenses',
                ))->afterCommit()));
        } elseif ($licenseRenewalReview->status === 'pending_management_review') {
            abort_unless($request->user()->can('licenses.review.renewals'), 403);
            $licenseRenewalReview->update([
                'status' => 'complete',
                'management_reviewed_by_id' => $request->user()->id,
                'management_reviewed_at' => now(),
                'management_notes' => $data['notes'],
            ]);
        } else {
            throw ValidationException::withMessages(['status' => 'This renewal review is already complete.']);
        }

        return response()->json(['data' => $licenseRenewalReview->fresh(['license'])]);
    }
}
