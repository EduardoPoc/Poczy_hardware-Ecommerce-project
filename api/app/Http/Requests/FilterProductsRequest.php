<?php

namespace App\Http\Requests;

use App\Services\CategoryService;
use App\Services\SpecificationService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use JsonException;

class FilterProductsRequest extends PaginationRequest
{
    protected function prepareForValidation(): void
    {
        $filter = $this->query('filter');
        if (is_string($filter)) {
            try {
                $filter = json_decode($filter, true, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw ValidationException::withMessages(['filter' => 'O filtro deve ser um JSON válido.']);
            }
        }
        $this->merge(['filter' => $filter]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'filter' => ['required', 'array:category_id,min_price,max_price,brand_ids,specifications'],
            'filter.category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'filter.min_price' => ['sometimes', 'numeric', 'min:0', 'max:99999999.99'],
            'filter.max_price' => ['sometimes', 'numeric', 'min:0', 'max:99999999.99'],
            'filter.brand_ids' => ['sometimes', 'array', 'list', 'max:100'],
            'filter.brand_ids.*' => ['integer', 'distinct', Rule::exists('brands', 'id')],
            'filter.specifications' => ['sometimes', 'array'],
            'filter.specifications.*' => ['array', 'list', 'max:100'],
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $filter = $this->input('filter');
            if (isset($filter['min_price'], $filter['max_price']) && $filter['min_price'] > $filter['max_price']) {
                $validator->errors()->add('filter.max_price', 'O preço máximo deve ser maior ou igual ao mínimo.');
            }
            $category = app(CategoryService::class)->getPublicCategory((int) $filter['category_id']);
            $definitions = $category->getAttribute('attributes') ?? [];
            if (! is_array($definitions)) {
                $validator->errors()->add('filter.category_id', 'A categoria não possui definições válidas de filtros.');

                return;
            }
            foreach ($filter['specifications'] ?? [] as $key => $values) {
                $field = 'filter.specifications.'.$key;
                $definition = $definitions[$key] ?? null;
                if (! is_string($key) || ! preg_match('/^[a-z][a-z0-9_]{0,59}$/', $key)
                    || $key === 'brand' || ! is_array($definition) || ! ($definition['is_filterable'] ?? true)) {
                    $validator->errors()->add($field, 'Este atributo não está disponível como filtro.');

                    continue;
                }
                foreach ($values as $index => $value) {
                    if (! app(SpecificationService::class)->matchesDefinition($value, $definition)) {
                        $validator->errors()->add($field.'.'.$index, 'O valor não corresponde ao tipo, opções ou limites do atributo.');
                    }
                }
            }
        });
    }
}
