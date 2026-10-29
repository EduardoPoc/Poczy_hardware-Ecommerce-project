<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CatalogProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'category_id' => $this->category_id,
            'brand' => $this->whenLoaded('brand', fn (): string => $this->brand->name),
            'name' => $this->name, 'description' => $this->description,
            'specifications' => $this->specifications ?? (object) [],
            'price' => $this->price, 'image_url' => $this->image_url,
        ];
    }
}
