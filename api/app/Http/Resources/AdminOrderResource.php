<?php

namespace App\Http\Resources;

use App\Services\OrderService;
use Illuminate\Http\Request;

class AdminOrderResource extends OrderResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'allowed_statuses' => OrderService::allowedTransitions($this->resource),
            'items' => AdminOrderItemResource::collection($this->whenLoaded('items')),
        ]);
    }
}
