<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-shop') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')],
            'description' => ['nullable', 'string', 'max:10000'],
            'attributes' => ['nullable', 'array', 'max:100'],
            'attributes.*' => ['required', 'array:name,type,unit,options,required,is_filterable,minimum,maximum,exclusive_minimum,exclusive_maximum'],
            'attributes.*.name' => ['required', 'string', 'max:100'],
            'attributes.*.type' => ['required', Rule::in(['text', 'integer', 'number', 'boolean'])],
            'attributes.*.unit' => ['nullable', 'string', 'max:30'],
            'attributes.*.required' => ['sometimes', 'boolean:strict'],
            'attributes.*.is_filterable' => ['sometimes', 'boolean:strict'],
            'attributes.*.options' => ['sometimes', 'array', 'list', 'min:1', 'max:100'],
            'attributes.*.minimum' => ['sometimes', 'numeric:strict'],
            'attributes.*.maximum' => ['sometimes', 'numeric:strict'],
            'attributes.*.exclusive_minimum' => ['sometimes', 'numeric:strict'],
            'attributes.*.exclusive_maximum' => ['sometimes', 'numeric:strict'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
