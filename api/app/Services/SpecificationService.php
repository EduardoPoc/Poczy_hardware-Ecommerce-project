<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class SpecificationService
{
    /** @param array<string, mixed> $definition */
    public function matchesDefinition(mixed $value, array $definition): bool
    {
        $type = $definition['type'] ?? null;
        $validType = match ($type) {
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'text' => is_string($value) && mb_strlen($value) <= 1000,
            default => false,
        };
        if (! $validType || (is_float($value) && ! is_finite($value))) {
            return false;
        }
        if (isset($definition['options'])) {
            if (! is_array($definition['options'])) {
                return false;
            }
            $allowed = in_array($value, $definition['options'], true);
            if (in_array($type, ['integer', 'number'], true)) {
                $allowed = collect($definition['options'])->contains(fn (mixed $option): bool => (is_int($option) || is_float($option)) && $value == $option);
            }
            if (! $allowed) {
                return false;
            }
        }
        if (in_array($type, ['integer', 'number'], true)) {
            foreach (['minimum', 'maximum', 'exclusive_minimum', 'exclusive_maximum'] as $bound) {
                if (! isset($definition[$bound])) {
                    continue;
                }
                if (! is_numeric($definition[$bound]) || ! is_finite((float) $definition[$bound])) {
                    return false;
                }
                if (! match ($bound) {
                    'minimum' => $value >= $definition[$bound],
                    'maximum' => $value <= $definition[$bound],
                    'exclusive_minimum' => $value > $definition[$bound],
                    'exclusive_maximum' => $value < $definition[$bound],
                }) {
                    return false;
                }
            }
        }

        return true;
    }

    /** @param array<string, array<string, mixed>> $definitions */
    public function validateDefinitions(array $definitions): void
    {
        foreach ($definitions as $key => $definition) {
            $field = 'attributes.'.$key;
            if (! is_string($key) || ! preg_match('/^[a-z][a-z0-9_]{0,59}$/', $key)) {
                throw ValidationException::withMessages([$field => 'A chave deve usar snake_case e ter até 60 caracteres.']);
            }
            if ($key === 'brand' && (($definition['type'] ?? null) !== 'text' || ($definition['required'] ?? false))) {
                throw ValidationException::withMessages([$field => 'Marca é um filtro textual reservado; use brand_id no produto.']);
            }
            $bounds = array_intersect_key($definition, array_flip(['minimum', 'maximum', 'exclusive_minimum', 'exclusive_maximum']));
            if ($bounds !== [] && ! in_array($definition['type'], ['integer', 'number'], true)) {
                throw ValidationException::withMessages([$field => 'Somente atributos numéricos aceitam limites.']);
            }
            foreach ($bounds as $bound) {
                if (! is_finite((float) $bound)) {
                    throw ValidationException::withMessages([$field => 'Os limites devem ser finitos.']);
                }
            }
            foreach (['minimum', 'exclusive_minimum'] as $lower) {
                foreach (['maximum', 'exclusive_maximum'] as $upper) {
                    if (isset($definition[$lower], $definition[$upper]) &&
                        ($definition[$lower] > $definition[$upper] ||
                        ($definition[$lower] == $definition[$upper] && ($lower === 'exclusive_minimum' || $upper === 'exclusive_maximum')))) {
                        throw ValidationException::withMessages([$field => 'Os limites não permitem nenhum valor.']);
                    }
                }
            }
            $withoutOptions = $definition;
            unset($withoutOptions['options']);
            foreach ($definition['options'] ?? [] as $option) {
                if (! $this->matchesDefinition($option, $withoutOptions)) {
                    throw ValidationException::withMessages([$field.'.options' => 'As opções devem respeitar o tipo e os limites do atributo.']);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, array<string, mixed>>  $definitions
     */
    public function validateValues(array $values, array $definitions, string $field = 'specifications'): void
    {
        $errors = [];
        foreach ($definitions as $key => $definition) {
            if ($key !== 'brand' && ($definition['required'] ?? false) && ! array_key_exists($key, $values)) {
                $errors[$field.'.'.$key] = 'Este atributo é obrigatório para a categoria.';
            }
        }
        foreach ($values as $key => $value) {
            if ($key === 'brand' || ! isset($definitions[$key]) || ! $this->matchesDefinition($value, $definitions[$key])) {
                $errors[$field.'.'.$key] = 'O atributo deve existir na categoria e respeitar seu tipo, opções e limites.';
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
