<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'categories' => CatalogCategoryResource::collection($this->resource['categories']),
            'products' => CatalogProductResource::collection($this->resource['products']),
        ];
    }
}
