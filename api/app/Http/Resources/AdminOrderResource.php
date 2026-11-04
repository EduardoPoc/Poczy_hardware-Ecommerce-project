<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class AdminOrderResource extends OrderResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'items' => AdminOrderItemResource::collection($this->whenLoaded('items')),
        ]);
    }
}
