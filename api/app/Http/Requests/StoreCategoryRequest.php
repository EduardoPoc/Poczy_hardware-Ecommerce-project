<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')],
            'description' => ['nullable', 'string'],
            'attributes' => ['nullable', 'array', 'max:100'],
            'attributes.*' => ['array:name,type,unit,options,required,is_filterable,minimum,maximum,exclusive_minimum,exclusive_maximum'],
            'attributes.*.name' => ['required', 'string'],
            'attributes.*.type' => ['required', Rule::in(['text', 'integer', 'number', 'boolean'])],
            'attributes.*.unit' => ['sometimes', 'nullable', 'string'],
            'attributes.*.options' => ['sometimes', 'array'],
            'attributes.*.required' => ['sometimes', 'boolean:strict'],
            'attributes.*.is_filterable' => ['sometimes', 'boolean:strict'],
            'attributes.*.minimum' => ['sometimes', 'numeric:strict'],
            'attributes.*.maximum' => ['sometimes', 'numeric:strict'],
            'attributes.*.exclusive_minimum' => ['sometimes', 'numeric:strict'],
            'attributes.*.exclusive_maximum' => ['sometimes', 'numeric:strict'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
