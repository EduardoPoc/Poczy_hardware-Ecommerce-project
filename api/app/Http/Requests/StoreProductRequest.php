<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:65535', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && strlen($value) > 65535) {
                    $fail('A descrição deve ter no máximo 65535 bytes.');
                }
            }],
            'specifications' => ['nullable', 'array', 'max:100'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'cost' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'stock' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'image_url' => ['nullable', 'url:http,https', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
