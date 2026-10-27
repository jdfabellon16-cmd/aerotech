<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('purchase_orders.view'), 403);

        $purchaseOrders = PurchaseOrder::query()
            ->with(['supplier', 'purchaseRequest', 'items.category'])
            ->latest('id')
            ->paginate(20);

        return response()->json($purchaseOrders);
    }

    public function store(StorePurchaseOrderRequest $request, PurchaseOrderService $service): JsonResponse
    {
        $purchaseOrder = $service->create($request->user(), $request->validated());

        return response()->json(['data' => $purchaseOrder], 201);
    }
}
