<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartMergeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'cart' => CartResource::make($this->resource['cart']),
            'adjustments' => $this->resource['adjustments'],
        ];
    }
}
