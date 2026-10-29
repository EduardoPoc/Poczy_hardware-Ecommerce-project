<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'total' => $this->total,
            'created_at' => $this->created_at?->toISOString(),
            'items_quantity' => (int) ($this->items_quantity ?? 0),
            'items_count' => (int) $this->items_count,
        ];
    }
}
