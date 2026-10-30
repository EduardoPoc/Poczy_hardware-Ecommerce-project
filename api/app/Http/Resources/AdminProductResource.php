<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class AdminProductResource extends ProductDetailResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'category_id' => $this->category_id,
            'brand_id' => $this->brand_id,
            'cost' => $this->cost,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ]);
    }
}
