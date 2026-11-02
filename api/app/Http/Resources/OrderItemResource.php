<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'product_id' => $this->product_id,
            'category_id' => $this->category_id,
            'category_name_snapshot' => $this->category_name_snapshot,
            'product_name_snapshot' => $this->product_name_snapshot,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'line_total' => $this->whenHas('line_total'),
        ];
    }
}
