<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'items' => CartItemResource::collection($this->items),
            'items_count' => $this->items_count,
            'items_quantity' => $this->items_quantity,
            'products_total' => $this->products_total,
            'cart_token' => $this->when($request->attributes->has('issued_cart_token'), fn (): string => $request->attributes->get('issued_cart_token')),
        ];
    }
}
