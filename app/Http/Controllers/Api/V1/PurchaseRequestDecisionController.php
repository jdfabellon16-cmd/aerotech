<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DecidePurchaseRequestRequest;
use App\Http\Resources\PurchaseRequestResource;
use App\Models\PurchaseRequest;
use App\Services\PurchaseRequestService;

class PurchaseRequestDecisionController extends Controller
{
    public function store(
        DecidePurchaseRequestRequest $request,
        PurchaseRequest $purchaseRequest,
        PurchaseRequestService $service,
    ): PurchaseRequestResource {
        return new PurchaseRequestResource($service->decide(
            $purchaseRequest,
            $request->user(),
            $request->validated('decision'),
            $request->validated('reason'),
        ));
    }
}
