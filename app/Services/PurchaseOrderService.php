<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    public function create(User $creator, array $data): PurchaseOrder
    {
        if (! $creator->can('purchase_orders.manage')) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($creator, $data): PurchaseOrder {
            $request = PurchaseRequest::query()
                ->whereKey($data['purchase_request_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($request->status !== 'approved') {
                throw ValidationException::withMessages([
                    'purchase_request_id' => 'A purchase order can only be created for an approved request.',
                ]);
            }

            if (collect($data['items'])->sum('quantity') !== $request->quantity) {
                throw ValidationException::withMessages([
                    'items' => 'The purchase order quantity must match the approved request quantity.',
                ]);
            }

            foreach ($data['items'] as $item) {
                if ((int) $item['asset_category_id'] !== $request->asset_category_id) {
                    throw ValidationException::withMessages([
                        'items' => 'Purchase order items must match the approved asset category.',
                    ]);
                }
            }

            $purchaseOrder = PurchaseOrder::create([
                ...collect($data)->except('items')->all(),
                'created_by_id' => $creator->id,
                'status' => 'ordered',
            ]);
            $purchaseOrder->items()->createMany($data['items']);

            return $purchaseOrder->load(['supplier', 'purchaseRequest', 'items.category']);
        });
    }
}
