<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResubmitPurchaseRequestRequest;
use App\Http\Resources\PurchaseRequestResource;
use App\Models\PurchaseRequest;
use App\Services\PurchaseRequestService;

class PurchaseRequestResubmissionController extends Controller
{
    public function store(
        ResubmitPurchaseRequestRequest $request,
        PurchaseRequest $purchaseRequest,
        PurchaseRequestService $service,
    ): PurchaseRequestResource {
        return new PurchaseRequestResource($service->resubmit(
            $purchaseRequest,
            $request->user(),
            $request->validated(),
        ));
    }
}
