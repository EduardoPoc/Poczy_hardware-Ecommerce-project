<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-shop') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['INBOUND', 'OUTBOUND', 'ADJUSTMENT'])],
            'change' => ['required', 'integer', 'between:-2147483647,2147483647', 'not_in:0'],
            'reason' => ['required', 'string', 'max:150'],
        ];
    }
}
