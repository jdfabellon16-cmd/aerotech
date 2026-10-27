<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResubmitPurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'asset_category_id' => ['required', 'integer', 'exists:asset_categories,id'],
            'justification' => ['required', 'string', 'max:5000'],
            'urgency' => ['required', 'in:low,normal,high,critical'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
