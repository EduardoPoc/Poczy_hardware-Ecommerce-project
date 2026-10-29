<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'product_id' => $this->product_id,
            'type' => $this->type, 'quantity' => $this->quantity, 'change' => $this->change,
            'reason' => $this->reason, 'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
