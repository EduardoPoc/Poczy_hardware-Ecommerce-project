<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $category = Category::create(['name' => 'Peças']);
        $this->product = Product::create([
            'category_id' => $category->id, 'name' => 'Produto', 'price' => '0.10', 'cost' => '0.05', 'stock' => 10,
        ])->refresh();
    }

    /** @return array{0: int, 1: string, 2: string} */
    private function guestCart(): array
    {
        $response = $this->getJson('/api/cart')->assertOk();

        return [$response->json('data.id'), $response->json('data.cart_token'), session('guest_cart_binding')];
    }

    public function test_guest_cart_issues_an_opaque_token_stores_only_its_hash_and_reuses_its_session(): void
    {
        [$id, $token] = $this->guestCart();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        $this->assertDatabaseHas('carts', ['id' => $id, 'user_id' => null, 'token_hash' => hash('sha256', $token)]);
        $this->getJson('/api/cart', ['X-Cart-Token' => $token])->assertOk()
            ->assertJsonPath('data.id', $id)->assertJsonMissingPath('data.cart_token')
            ->assertJsonMissingPath('data.token_hash')->assertJsonMissingPath('data.session_binding_hash');
        $this->getJson('/api/cart')->assertOk()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('carts', 1);
    }

    public function test_a_token_cannot_be_used_by_another_visitor_or_guessed_in_the_owner_session(): void
    {
        [$id, $token, $binding] = $this->guestCart();
        $this->getJson('/api/cart', ['X-Cart-Token' => str_repeat('a', 64)])->assertNotFound();
        $this->withSession(['guest_cart_id' => null, 'guest_cart_binding' => null])
            ->getJson('/api/cart', ['X-Cart-Token' => $token])->assertNotFound();
        $this->withSession(['guest_cart_id' => $id, 'guest_cart_binding' => bin2hex(random_bytes(32))])
            ->getJson('/api/cart', ['X-Cart-Token' => $token])->assertNotFound();
        $this->withSession(['guest_cart_id' => $id, 'guest_cart_binding' => $binding])
            ->getJson('/api/cart', ['X-Cart-Token' => $token])->assertOk();
    }

    public function test_repeated_addition_sums_quantities_and_keeps_one_item_without_reserving_stock(): void
    {
        [$id, $token] = $this->guestCart();
        $headers = ['X-Cart-Token' => $token];
        $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 2], $headers)
            ->assertOk()->assertJsonPath('data.items.0.quantity', 2);
        $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 3], $headers)
            ->assertOk()->assertJsonPath('data.items_count', 1)->assertJsonPath('data.items.0.quantity', 5)
            ->assertJsonPath('data.products_total', '0.50')->assertJsonMissingPath('data.items.0.product.cost');
        $this->assertDatabaseHas('cart_items', ['cart_id' => $id, 'product_id' => $this->product->id, 'quantity' => 5]);
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_first_item_addition_without_getting_a_cart_returns_a_reusable_token(): void
    {
        $response = $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 2])
            ->assertOk()->assertJsonPath('data.items.0.quantity', 2);
        $token = $response->json('data.cart_token');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        $this->getJson('/api/cart', ['X-Cart-Token' => $token])->assertOk()
            ->assertJsonPath('data.id', $response->json('data.id'))->assertJsonPath('data.items.0.quantity', 2);
    }

    public function test_failed_first_mutation_rolls_back_the_guest_cart_and_allows_token_recovery(): void
    {
        $this->postJson('/api/cart/items', ['product_id' => 999999, 'quantity' => 1])->assertNotFound();
        $this->assertDatabaseCount('carts', 0);
        $this->assertFalse(session()->has('guest_cart_id'));
        $this->assertFalse(session()->has('guest_cart_binding'));
        [$id, $token] = $this->guestCart();
        $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 1], ['X-Cart-Token' => $token])
            ->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.items.0.quantity', 1);
        $this->assertDatabaseCount('carts', 1);
    }

    public function test_updates_replace_quantity_and_zero_or_delete_removes_the_item(): void
    {
        [, $token] = $this->guestCart();
        $headers = ['X-Cart-Token' => $token];
        $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 5], $headers)->assertOk();
        $this->patchJson('/api/cart/items/'.$this->product->id, ['quantity' => 2], $headers)
            ->assertOk()->assertJsonPath('data.items.0.quantity', 2);
        $this->patchJson('/api/cart/items/'.$this->product->id, ['quantity' => 0], $headers)
            ->assertOk()->assertJsonCount(0, 'data.items');
        $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 1], $headers)->assertOk();
        $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 0], $headers)
            ->assertOk()->assertJsonCount(0, 'data.items');
        $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 1], $headers)->assertOk();
        $this->deleteJson('/api/cart/items/'.$this->product->id, [], $headers)->assertOk()->assertJsonCount(0, 'data.items');
    }

    public function test_quantities_cannot_exceed_stock_and_failed_mutations_preserve_existing_items(): void
    {
        [, $token] = $this->guestCart();
        $headers = ['X-Cart-Token' => $token];
        $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 8], $headers)->assertOk();
        $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 3], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->patchJson('/api/cart/items/'.$this->product->id, ['quantity' => 11], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->assertDatabaseHas('cart_items', ['product_id' => $this->product->id, 'quantity' => 8]);
    }

    public function test_inactive_products_categories_and_empty_stock_cannot_be_added_but_items_can_be_removed(): void
    {
        [, $token] = $this->guestCart();
        $headers = ['X-Cart-Token' => $token];
        $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 1], $headers)->assertOk();
        $this->product->update(['is_active' => false]);
        $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 1], $headers)
            ->assertUnprocessable()->assertJsonValidationErrors('product_id');
        $this->patchJson('/api/cart/items/'.$this->product->id, ['quantity' => 0], $headers)->assertOk()->assertJsonCount(0, 'data.items');
        $this->product->update(['is_active' => true, 'stock' => 0]);
        $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 1], $headers)->assertUnprocessable();
        $this->product->update(['stock' => 10]);
        $this->product->category()->update(['is_active' => false]);
        $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 1], $headers)->assertUnprocessable();
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_cart_prices_and_totals_follow_current_product_prices_without_float_arithmetic(): void
    {
        [, $token] = $this->guestCart();
        $headers = ['X-Cart-Token' => $token];
        $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 3], $headers)
            ->assertOk()->assertJsonPath('data.products_total', '0.30');
        $this->product->update(['price' => '0.20']);
        $this->getJson('/api/cart', $headers)->assertOk()->assertJsonPath('data.items.0.unit_price', '0.20')
            ->assertJsonPath('data.items.0.line_total', '0.60')->assertJsonPath('data.products_total', '0.60');
    }

    public function test_cart_total_adds_multiple_products_with_exact_decimal_amounts(): void
    {
        $other = Product::create([
            'category_id' => $this->product->category_id, 'name' => 'Outro produto', 'price' => '0.20', 'stock' => 10,
        ]);
        [, $token] = $this->guestCart();
        $headers = ['X-Cart-Token' => $token];
        $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 3], $headers)->assertOk();
        $this->postJson('/api/cart/items', ['product_id' => $other->id, 'quantity' => 7], $headers)
            ->assertOk()->assertJsonPath('data.items_count', 2)->assertJsonPath('data.items_quantity', 10)
            ->assertJsonPath('data.items.0.line_total', '0.30')->assertJsonPath('data.items.1.line_total', '1.40')
            ->assertJsonPath('data.products_total', '1.70');
    }

    public function test_a_product_deleted_between_loading_items_and_products_does_not_break_cart_serialization(): void
    {
        [, $token] = $this->guestCart();
        $headers = ['X-Cart-Token' => $token];
        $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 1], $headers)->assertOk();
        $productId = $this->product->id;
        CartItem::retrieved(function (CartItem $item) use ($productId): void {
            if ($item->product_id === $productId) {
                Product::whereKey($productId)->delete();
            }
        });
        try {
            $this->getJson('/api/cart', $headers)->assertOk()
                ->assertJsonCount(0, 'data.items')->assertJsonPath('data.products_total', '0.00');
        } finally {
            CartItem::flushEventListeners();
        }
        $this->assertDatabaseMissing('cart_items', ['product_id' => $productId]);
    }

    public function test_each_account_reuses_one_cart_and_cannot_select_another_users_cart(): void
    {
        $user = User::create(['name' => 'Cliente', 'email' => 'cart@example.com', 'password' => 'SenhaCliente!2026']);
        $other = User::create(['name' => 'Outro', 'email' => 'othercart@example.com', 'password' => 'SenhaCliente!2026']);
        $otherCart = Cart::factory()->create(['user_id' => $other->id, 'token_hash' => null, 'session_binding_hash' => null]);
        $this->actingAs($user, 'web');
        $id = $this->getJson('/api/cart')->assertOk()->assertJsonPath('data.user_id', $user->id)->json('data.id');
        $this->getJson('/api/cart?cart_id='.$otherCart->id)->assertOk()->assertJsonPath('data.id', $id);
        $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 1, 'cart_id' => $otherCart->id])->assertOk();
        $this->assertDatabaseCount('carts', 2);
        $this->assertDatabaseHas('cart_items', ['cart_id' => $id, 'product_id' => $this->product->id]);
        $this->assertDatabaseMissing('cart_items', ['cart_id' => $otherCart->id]);
    }

    public function test_cart_item_input_requires_a_product_and_integer_non_negative_quantity(): void
    {
        $this->postJson('/api/cart/items', [])->assertUnprocessable()->assertJsonValidationErrors(['product_id', 'quantity']);
        foreach ([-1, 1.5, 'invalid', null, 2147483648] as $quantity) {
            $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => $quantity])
                ->assertUnprocessable()->assertJsonValidationErrors('quantity');
        }
        $this->postJson('/api/cart/items', ['product_id' => 999999, 'quantity' => 1])->assertNotFound();
    }

    public function test_database_enforces_one_cart_per_user(): void
    {
        $user = User::create(['name' => 'Cliente', 'email' => 'unique@example.com', 'password' => 'SenhaCliente!2026']);
        Cart::factory()->create(['user_id' => $user->id]);
        $this->expectException(QueryException::class);
        Cart::factory()->create(['user_id' => $user->id]);
    }

    public function test_database_enforces_one_item_per_cart_and_product(): void
    {
        $item = CartItem::factory()->create(['product_id' => $this->product->id]);
        $this->expectException(QueryException::class);
        CartItem::factory()->create(['cart_id' => $item->cart_id, 'product_id' => $this->product->id]);
    }
}
