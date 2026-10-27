<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseRequestRequest;
use App\Http\Resources\PurchaseRequestResource;
use App\Models\AssetCategory;
use App\Models\PurchaseRequest;
use App\Services\PurchaseRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PurchaseRequestController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = PurchaseRequest::query()
            ->with(['requester', 'department', 'category', 'approvals.approver'])
            ->latest('id');

        if (! $request->user()->can('purchase_requests.view_all')) {
            $query->where('requested_by_id', $request->user()->id);
        }

        return PurchaseRequestResource::collection($query->paginate(20));
    }

    public function store(StorePurchaseRequestRequest $request, PurchaseRequestService $service): JsonResponse
    {
        $purchaseRequest = $service->create($request->user(), $request->validated());

        return (new PurchaseRequestResource($purchaseRequest))
            ->response()
            ->setStatusCode(201);
    }

    public function show(PurchaseRequest $purchaseRequest, Request $request): PurchaseRequestResource
    {
        if (
            $purchaseRequest->requested_by_id !== $request->user()->id
            && ! $request->user()->can('purchase_requests.view_all')
        ) {
            Gate::authorize('purchase_requests.view_all');
        }

        return new PurchaseRequestResource(
            $purchaseRequest->load(['requester', 'department', 'category', 'approvals.approver']),
        );
    }

    public function categories(): JsonResponse
    {
        return response()->json([
            'data' => AssetCategory::query()->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }
}
