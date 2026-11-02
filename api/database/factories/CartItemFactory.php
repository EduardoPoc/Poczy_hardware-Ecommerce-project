<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CartItem> */
class CartItemFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'cart_id' => Cart::factory(),
            'product_id' => fn (): int => Product::create([
                'category_id' => Category::create(['name' => fake()->unique()->bothify('Categoria-########')])->id,
                'name' => fake()->words(3, true),
                'price' => '10.00',
                'stock' => 10,
            ])->id,
            'quantity' => 1,
        ];
    }
}
