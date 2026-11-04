<?php

namespace App\Http\Requests;

class UpdateAddressRequest extends StoreAddressRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        foreach (['postal_code', 'street', 'number', 'city', 'state'] as $field) {
            array_unshift($rules[$field], 'sometimes');
        }

        return $rules;
    }
}
