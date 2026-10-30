<?php

namespace App\Http\Requests;

class UpdateAddressRequest extends StoreAddressRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return collect(parent::rules())->map(fn (array $rules): array => array_map(
            fn (mixed $rule): mixed => $rule === 'required' ? 'sometimes' : $rule,
            $rules,
        ))->all();
    }
}
