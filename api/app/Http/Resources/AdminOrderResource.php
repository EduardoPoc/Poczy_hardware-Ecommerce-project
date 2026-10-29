<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class AdminOrderResource extends OrderDetailResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), ['user_id' => $this->user_id]);
    }
}
