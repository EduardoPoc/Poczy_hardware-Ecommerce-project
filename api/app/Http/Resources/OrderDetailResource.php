<?php

namespace App\Http\Resources;

use App\Models\OrderStatusHistory;
use Illuminate\Http\Request;

class OrderDetailResource extends OrderSummaryResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'products_total' => $this->products_total,
            'shipping_total' => $this->shipping_total,
            'updated_at' => $this->updated_at?->toISOString(),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'shipping_address' => new ShippingAddressResource($this->whenLoaded('shippingAddress')),
            'timeline' => $this->whenLoaded('statusHistory', fn () => $this->statusHistory->map(fn (OrderStatusHistory $event): array => [
                'id' => $event->id,
                'status' => $event->status,
                'changed_at' => $event->changed_at?->toISOString(),
            ])),
        ]);
    }
}
