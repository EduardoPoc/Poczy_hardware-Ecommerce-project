<?php

namespace App\Http\Resources;

use App\Models\CartItem;
use Brick\Math\BigDecimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $total = BigDecimal::zero()->toScale(2);
        foreach ($this->items as $item) {
            $total = $total->plus(BigDecimal::of($item->product->price)->multipliedBy($item->quantity));
        }

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'items' => CartItemResource::collection($this->items),
            'items_count' => $this->items->count(),
            'items_quantity' => $this->items->sum(fn (CartItem $item): int => $item->quantity),
            'products_total' => (string) $total->toScale(2),
            'cart_token' => $this->when($request->attributes->has('issued_cart_token'), fn (): string => $request->attributes->get('issued_cart_token')),
        ];
    }
}
