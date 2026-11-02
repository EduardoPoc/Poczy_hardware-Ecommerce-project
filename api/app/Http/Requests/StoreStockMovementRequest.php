<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['INBOUND', 'OUTBOUND', 'ADJUSTMENT'])],
            'quantity' => ['required', 'integer', 'not_in:0', 'max:2147483647', $this->input('type') === 'ADJUSTMENT' ? 'min:-2147483647' : 'min:1'],
            'reason' => ['required', 'string', 'max:150'],
        ];
    }
}
