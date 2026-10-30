<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateBrandRequest extends StoreBrandRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:100', Rule::unique('brands', 'name')->ignore($this->route('brand'))]];
    }
}
