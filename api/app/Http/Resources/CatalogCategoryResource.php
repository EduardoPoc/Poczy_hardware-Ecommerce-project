<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogCategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'attributes' => collect($this->resource->getAttribute('attributes') ?? [])
                ->filter(fn (array $definition): bool => (bool) ($definition['is_filterable'] ?? true))
                ->map(fn (array $definition, string $key): array => ['key' => $key] + $definition)
                ->sortBy('name')->values(),
        ];
    }
}
