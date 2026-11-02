<?php

namespace App\Http\Requests;

class FilterPublicProductsRequest extends FilterProductsRequest
{
    public function validationData(): array
    {
        $data = parent::validationData();
        $filter = array_key_exists('filter', $data) ? $data['filter'] : [];
        if (is_string($filter)) {
            $decoded = json_decode($filter, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $filter = $decoded;
            }
        }
        $data['filter'] = $filter;

        return $data;
    }

    public function rules(): array
    {
        $rules = ['filter' => ['sometimes', 'array']];
        foreach (parent::rules() as $field => $validation) {
            if ($field === 'is_active') {
                continue;
            }
            if ($field === 'max_price') {
                $validation = array_values(array_filter($validation, fn (mixed $rule): bool => $rule !== 'gte:min_price'));
            }
            if (in_array($field, ['page', 'per_page'], true)) {
                $rules[$field] = $validation;
            } else {
                $rules['filter.'.$field] = $validation;
            }
        }
        if (isset($this->validationData()['filter']['min_price'])) {
            $rules['filter.max_price'][] = 'gte:filter.min_price';
        }

        return $rules;
    }
}
