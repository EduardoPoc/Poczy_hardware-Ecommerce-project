<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCheckoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'address_id' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', Rule::in(['FAKE_PIX'])],
            'checkout_token' => ['required', 'uuid'],
            'expected_total' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'items' => ['prohibited'],
            'user_id' => ['prohibited'],
            'status' => ['prohibited'],
            'products_total' => ['prohibited'],
            'shipping_total' => ['prohibited'],
            'total' => ['prohibited'],
            'card_number' => ['prohibited'],
        ];
    }
}
