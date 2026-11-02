<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'address_id' => ['required', 'integer'],
            'payment_method' => ['required', Rule::in(['PIX', 'CREDIT_CARD', 'BOLETO'])],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'array:product_id,quantity'],
            'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'between:1,10000'],
            'user_id' => ['prohibited'],
            'status' => ['prohibited'],
            'products_total' => ['prohibited'],
            'shipping_total' => ['prohibited'],
            'total' => ['prohibited'],
        ];
    }
}
