<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReceivePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Services\AssetReceivingService;
use Illuminate\Http\JsonResponse;

class PurchaseOrderReceivingController extends Controller
{
    public function store(
        ReceivePurchaseOrderRequest $request,
        PurchaseOrder $purchaseOrder,
        AssetReceivingService $service,
    ): JsonResponse {
        $updatedPurchaseOrder = $service->receive(
            $purchaseOrder,
            $request->user(),
            $request->validated('units'),
        );

        return response()->json(['data' => $updatedPurchaseOrder]);
    }
}
