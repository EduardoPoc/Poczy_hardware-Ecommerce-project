<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class CategoryDetailResource extends CategoryResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'description' => $this->description,
            'attributes' => $this->resource->getAttribute('attributes') ?? (object) [],
            'is_active' => $this->is_active,
        ]);
    }
}
