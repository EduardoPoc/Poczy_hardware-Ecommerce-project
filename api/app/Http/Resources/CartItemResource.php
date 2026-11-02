<?php

namespace App\Http\Resources;

use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'unit_price' => $this->product->price,
            'line_total' => (string) BigDecimal::of($this->product->price)->multipliedBy($this->quantity)->toScale(2),
            'product' => ProductResource::make($this->whenLoaded('product')),
        ];
    }
}
