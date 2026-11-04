<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'brand_id' => $this->brand_id,
            'name' => $this->name,
            'description' => $this->description,
            'specifications' => $this->specifications,
            'price' => $this->price,
            'stock' => $this->stock,
            'image_url' => $this->image_url,
            'is_active' => $this->is_active,
            'category' => $this->whenLoaded('category', fn (): array => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'brand' => BrandResource::make($this->whenLoaded('brand')),
        ];
    }
}
