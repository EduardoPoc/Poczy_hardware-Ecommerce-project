<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class SpecificationService
{
    private const NUMERIC_TYPES = ['integer', 'number'];

    private const BOUNDS = ['minimum', 'maximum', 'exclusive_minimum', 'exclusive_maximum'];

    /** @param array<string, mixed> $definition */
    public function matchesDefinition(mixed $value, array $definition): bool
    {
        $type = $definition['type'] ?? null;
        if (! $this->matchesType($value, $type)) {
            return false;
        }
        if (isset($definition['options']) && ! $this->matchesOptions($value, $type, $definition['options'])) {
            return false;
        }

        return ! in_array($type, self::NUMERIC_TYPES, true) || $this->matchesBounds($value, $definition);
    }

    private function matchesType(mixed $value, mixed $type): bool
    {
        $validType = match ($type) {
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'text' => is_string($value) && mb_strlen($value) <= 1000,
            default => false,
        };

        return $validType && (! is_float($value) || is_finite($value));
    }

    /** @param array<string, mixed> $definition */
    private function matchesBounds(int|float $value, array $definition): bool
    {
        foreach (self::BOUNDS as $bound) {
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
            $this->validateBounds($definition, $field);
            $this->validateOptions($definition, $field);
        }
    }

    /**
     * Opções numéricas aceitam 8 e 8.0 como equivalentes; outros tipos são estritos.
     * Em particular, false não equivale a zero nem a uma string vazia.
     */
    private function matchesOptions(mixed $value, string $type, mixed $options): bool
    {
        if (! is_array($options)) {
            return false;
        }
        if (in_array($type, self::NUMERIC_TYPES, true)) {
            return collect($options)->contains(fn (mixed $option): bool => (is_int($option) || is_float($option)) && $value == $option);
        }

        return in_array($value, $options, true);
    }

    /** @param array<string, mixed> $definition */
    private function validateBounds(array $definition, string $field): void
    {
        $bounds = array_intersect_key($definition, array_flip(self::BOUNDS));
        if ($bounds !== [] && ! in_array($definition['type'], self::NUMERIC_TYPES, true)) {
            throw ValidationException::withMessages([$field => 'Somente atributos numéricos aceitam limites.']);
        }
        foreach ($bounds as $bound) {
            if (! is_finite((float) $bound)) {
                throw ValidationException::withMessages([$field => 'Os limites devem ser finitos.']);
            }
        }
        foreach (['minimum', 'exclusive_minimum'] as $lower) {
            foreach (['maximum', 'exclusive_maximum'] as $upper) {
                if (! isset($definition[$lower], $definition[$upper])) {
                    continue;
                }
                $lowerValue = $definition[$lower];
                $upperValue = $definition[$upper];
                $excludesEqualBounds = $lower === 'exclusive_minimum' || $upper === 'exclusive_maximum';
                if ($lowerValue > $upperValue || ($lowerValue == $upperValue && $excludesEqualBounds)) {
                    throw ValidationException::withMessages([$field => 'Os limites não permitem nenhum valor.']);
                }
            }
        }
    }

    /** @param array<string, mixed> $definition */
    private function validateOptions(array $definition, string $field): void
    {
        $withoutOptions = $definition;
        unset($withoutOptions['options']);
        foreach ($definition['options'] ?? [] as $option) {
            if (! $this->matchesDefinition($option, $withoutOptions)) {
                throw ValidationException::withMessages([$field.'.options' => 'As opções devem respeitar o tipo e os limites do atributo.']);
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
