<?php

namespace App\Http\Requests;

use App\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReceivePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('receiving.manage') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'units' => ['required', 'array', 'min:1', 'max:100'],
            'units.*.purchase_order_item_id' => ['required', 'integer', 'exists:purchase_order_items,id'],
            'units.*.asset_tag' => ['required', 'string', 'max:50', 'distinct', Rule::unique('assets', 'asset_tag')],
            'units.*.serial_number' => ['nullable', 'string', 'max:100', 'distinct', Rule::unique('assets', 'serial_number')],
            'units.*.model' => ['nullable', 'string', 'max:255'],
            'units.*.purchase_date' => ['required', 'date'],
            'units.*.warranty_expires_at' => ['nullable', 'date', 'after_or_equal:units.*.purchase_date'],
            'units.*.location_id' => ['required', 'integer', 'exists:locations,id'],
            'units.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('units.*.purchase_order_item_id')) {
                    return;
                }

                $purchaseOrder = $this->route('purchaseOrder');
                if (! $purchaseOrder instanceof PurchaseOrder) {
                    return;
                }

                foreach ($this->input('units', []) as $index => $unit) {
                    $belongsToOrder = $purchaseOrder->items()
                        ->whereKey($unit['purchase_order_item_id'])
                        ->exists();

                    if (! $belongsToOrder) {
                        $validator->errors()->add(
                            "units.{$index}.purchase_order_item_id",
                            'The selected item does not belong to this purchase order.',
                        );
                    }
                }
            },
        ];
    }
}
