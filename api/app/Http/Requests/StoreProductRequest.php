<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $specifications = $this->input('specifications');

        if (is_string($specifications)) {

            $decoded = json_decode($specifications, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $this->merge(['specifications' => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:65535'],
            'specifications' => ['nullable', 'array', 'max:100'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'cost' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'stock' => ['sometimes', 'integer', 'min:0', 'max:4294967295'],
            'image' => ['bail', 'nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'extensions:png,jpg,jpeg,webp', 'max:5120'],
            'image_url' => ['prohibited'],
            'image_path' => ['prohibited'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'image.image' => 'Upload a valid PNG, JPG, JPEG, or WebP image.',
            'image.mimes' => 'Upload a PNG, JPG, JPEG, or WebP image.',
            'image.extensions' => 'The file must have a PNG, JPG, JPEG, or WebP extension.',
            'image.max' => 'The image must not exceed 5 MB.',
            'image.uploaded' => 'The image could not be uploaded. Check the file and the 5 MB limit.',
        ];
    }
}
