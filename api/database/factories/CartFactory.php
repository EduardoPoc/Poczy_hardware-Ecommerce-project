<?php

namespace Database\Factories;

use App\Models\Cart;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Cart> */
class CartFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'token_hash' => hash('sha256', bin2hex(random_bytes(32))),
            'session_binding_hash' => hash('sha256', bin2hex(random_bytes(32))),
        ];
    }
}
