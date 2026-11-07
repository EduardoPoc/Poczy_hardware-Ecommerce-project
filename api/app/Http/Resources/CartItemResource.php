<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'is_available' => $this->whenHas('is_available'),
            'unit_price' => $this->unit_price,
            'line_total' => $this->line_total,
            'product' => ProductResource::make($this->whenLoaded('product')),
        ];
    }
}
