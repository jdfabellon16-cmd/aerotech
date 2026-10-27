<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReceivingRecord;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssetReceivingService
{
    /**
     * @param array<int, array{
     *     purchase_order_item_id: int,
     *     asset_tag: string,
     *     serial_number?: string|null,
     *     model?: string|null,
     *     purchase_date: string,
     *     warranty_expires_at?: string|null,
     *     location_id: int,
     *     notes?: string|null
     * }> $units
     */
    public function receive(PurchaseOrder $purchaseOrder, User $receiver, array $units): PurchaseOrder
    {
        if (! $receiver->can('receiving.manage')) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($purchaseOrder, $receiver, $units): PurchaseOrder {
            $purchaseOrder = PurchaseOrder::query()
                ->whereKey($purchaseOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            foreach ($units as $unit) {
                $item = PurchaseOrderItem::query()
                    ->where('purchase_order_id', $purchaseOrder->id)
                    ->whereKey($unit['purchase_order_item_id'])
                    ->lockForUpdate()
                    ->first();

                if ($item === null) {
                    throw ValidationException::withMessages([
                        'units' => 'Each received unit must belong to this purchase order.',
                    ]);
                }

                if ($item->received_quantity >= $item->quantity) {
                    throw ValidationException::withMessages([
                        'units' => "The ordered quantity for {$item->model} has already been received.",
                    ]);
                }

                $asset = Asset::create([
                    'asset_tag' => $unit['asset_tag'],
                    'serial_number' => $unit['serial_number'] ?? null,
                    'asset_category_id' => $item->asset_category_id,
                    'model' => $unit['model'] ?? $item->model,
                    'supplier_id' => $purchaseOrder->supplier_id,
                    'purchase_order_item_id' => $item->id,
                    'purchase_date' => $unit['purchase_date'],
                    'warranty_expires_at' => $unit['warranty_expires_at'] ?? null,
                    'condition' => 'new',
                    'encryption_status' => 'unknown',
                    'location_id' => $unit['location_id'],
                    'status' => 'available',
                ]);

                ReceivingRecord::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'purchase_order_item_id' => $item->id,
                    'asset_id' => $asset->id,
                    'received_by_id' => $receiver->id,
                    'received_at' => now(),
                    'notes' => $unit['notes'] ?? null,
                ]);

                $item->increment('received_quantity');
            }

            $purchaseOrder->load('items');
            $allItemsReceived = $purchaseOrder->items->every(
                fn (PurchaseOrderItem $item): bool => $item->received_quantity >= $item->quantity,
            );
            $purchaseOrder->update(['status' => $allItemsReceived ? 'received' : 'partially_received']);

            return $purchaseOrder->fresh(['supplier', 'purchaseRequest', 'items.category', 'receivingRecords.asset']);
        });
    }
}
