<?php

namespace App\Http\Requests;

class UpdateProductRequest extends StoreProductRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = parent::rules();
        foreach (['category_id', 'name', 'price'] as $field) {
            array_unshift($rules[$field], 'sometimes');
        }
        $rules['stock'] = ['prohibited'];

        return $rules;
    }
}
