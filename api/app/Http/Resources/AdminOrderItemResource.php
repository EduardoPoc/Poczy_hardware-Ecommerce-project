<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class AdminOrderItemResource extends OrderItemResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), ['unit_cost' => $this->unit_cost]);
    }
}
