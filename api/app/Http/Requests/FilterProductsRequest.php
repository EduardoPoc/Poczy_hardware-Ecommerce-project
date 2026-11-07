<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function validationData(): array
    {
        $data = parent::validationData();
        $specifications = $data['specifications'] ?? null;
        if (is_string($specifications)) {
            $decoded = json_decode($specifications, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $data['specifications'] = $decoded;
            }
        }

        return $data;
    }

    public function rules(): array
    {
        $rules = [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'q' => ['sometimes', 'nullable', 'string', 'max:150'],
            'sort' => ['sometimes', Rule::in(['name', 'price_asc', 'price_desc', 'newest'])],
            'category_id' => ['sometimes', 'integer', 'min:1'],
            'brand_ids' => ['sometimes', 'array', 'max:100'],
            'brand_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'min_price' => ['sometimes', 'numeric', 'min:0', 'max:99999999.99'],
            'max_price' => ['sometimes', 'numeric', 'min:0', 'max:99999999.99'],
            'specifications' => ['bail', 'sometimes', 'array', 'max:100', function (string $attribute, mixed $value, Closure $fail): void {
                foreach (array_keys($value) as $key) {
                    if (! is_string($key) || ! preg_match('/^[a-z][a-z0-9_]{0,59}$/', $key)) {
                        $fail('Specification keys must use snake_case and must not exceed 60 characters.');
                    }
                }
            }],
            'specifications.*' => ['array', 'max:100'],
            'specifications.*.*' => [function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) && ! is_int($value) && ! is_float($value) && ! is_bool($value)) {
                    $fail('The filter value must be a string, number, or boolean.');
                }
            }],
        ];
        if ($this->filled('min_price')) {
            $rules['max_price'][] = 'gte:min_price';
        }
        if ($this->routeIs('admin.*')) {
            $rules['is_active'] = ['sometimes', 'boolean'];
        }

        return $rules;
    }
}
