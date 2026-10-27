<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('purchase_orders.manage') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'purchase_order_number' => ['required', 'string', 'max:100', Rule::unique('purchase_orders')],
            'purchase_request_id' => ['required', 'integer', 'exists:purchase_requests,id', Rule::unique('purchase_orders')],
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'ordered_at' => ['required', 'date'],
            'expected_at' => ['nullable', 'date', 'after_or_equal:ordered_at'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.asset_category_id' => ['required', 'integer', 'exists:asset_categories,id'],
            'items.*.model' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
        ];
    }
}
