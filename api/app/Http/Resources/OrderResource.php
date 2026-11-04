<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'products_total' => $this->products_total,
            'shipping_total' => $this->shipping_total,
            'total' => $this->total,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'items_count' => $this->whenCounted('items'),
            'items_quantity' => $this->whenHas('items_quantity', fn (): int => (int) $this->items_quantity),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'shipping_address' => OrderShippingAddressResource::make($this->whenLoaded('shippingAddress')),
            'status_history' => OrderStatusHistoryResource::collection($this->whenLoaded('statusHistory')),
        ];
    }
}
