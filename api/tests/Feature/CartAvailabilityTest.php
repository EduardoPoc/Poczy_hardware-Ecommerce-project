<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CartAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    private Product $product;

    private Product $otherProduct;

    private Cart $customerCart;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'availability-admin@example.com', 'password' => 'SenhaAdmin!2026']);
        $this->admin->forceFill(['role' => 'ADMIN'])->save();
        $this->customer = User::create(['name' => 'Cliente', 'email' => 'availability@example.com', 'password' => 'SenhaCliente!2026']);
        $category = Category::create(['name' => 'Componentes']);
        $otherCategory = Category::create(['name' => 'Periféricos']);
        $this->product = Product::create(['category_id' => $category->id, 'name' => 'Produto', 'price' => '19.90', 'stock' => 2])->refresh();
        $this->otherProduct = Product::create(['category_id' => $otherCategory->id, 'name' => 'Outro produto', 'price' => '9.90', 'stock' => 8])->refresh();
        $this->customerCart = Cart::factory()->create(['user_id' => $this->customer->id]);
        $adminCart = Cart::factory()->create(['user_id' => $this->admin->id]);
        $guest = Cart::factory()->create(['user_id' => null]);
        foreach ([$this->customerCart, $adminCart, $guest] as $cart) {
            $cart->items()->create(['product_id' => $this->product->id, 'quantity' => 1]);
            $cart->items()->create(['product_id' => $this->otherProduct->id, 'quantity' => 1]);
        }
        $this->actingAs($this->admin, 'web');
    }

    public function test_deactivation_preserves_items_and_marks_them_unavailable(): void
    {
        $this->patchJson('/api/admin/products/'.$this->product->id, ['is_active' => false])->assertOk();
        $this->assertDatabaseCount('cart_items', 6);
        $this->actingAs($this->customer, 'web')->getJson('/api/cart')->assertOk()
            ->assertJsonCount(2, 'data.items')->assertJsonPath('data.items.0.is_available', false);
        $this->patchJson('/api/cart/items/'.$this->product->id, ['quantity' => 0])->assertOk()->assertJsonCount(1, 'data.items');
    }

    public function test_stock_depletion_is_visible_and_replenishment_restores_availability(): void
    {
        $url = '/api/admin/products/'.$this->product->id.'/stock-movements';
        $this->postJson($url, ['type' => 'OUTBOUND', 'quantity' => 2])->assertCreated();
        $this->assertTrue($this->product->fresh()->is_active);
        $this->actingAs($this->customer, 'web')->getJson('/api/cart')->assertOk()->assertJsonPath('data.items.0.is_available', false);
        $this->actingAs($this->admin, 'web')->postJson($url, ['type' => 'INBOUND', 'quantity' => 2])->assertCreated();
        $this->actingAs($this->customer, 'web')->getJson('/api/cart')->assertOk()->assertJsonPath('data.items.0.is_available', true);
    }

    public function test_buying_last_units_clears_only_the_buyers_cart(): void
    {
        $this->product->update(['stock' => 1]);
        $address = $this->customer->addresses()->create(['postal_code' => '01001-000', 'street' => 'Rua de Entrega', 'number' => '1', 'city' => 'São Paulo', 'state' => 'SP']);
        $this->actingAs($this->customer, 'web')->postJson('/api/checkout', [
            'address_id' => $address->id, 'payment_method' => 'FAKE_PIX',
            'checkout_token' => (string) Str::uuid(), 'expected_total' => '59.80',
        ])->assertCreated()->assertJsonPath('data.status', 'PENDING');
        $this->getJson('/api/cart')->assertOk()->assertJsonCount(0, 'data.items');
        $this->assertDatabaseCount('cart_items', 4);
        $this->actingAs($this->admin, 'web')->getJson('/api/cart')->assertOk()->assertJsonPath('data.items.0.is_available', false);
    }

    public function test_category_deactivation_marks_its_cart_items_unavailable(): void
    {
        $this->patchJson('/api/admin/categories/'.$this->product->category_id, ['is_active' => false])->assertOk();
        $this->actingAs($this->customer, 'web')->getJson('/api/cart')->assertOk()->assertJsonPath('data.items.0.is_available', false);
        $this->assertDatabaseCount('cart_items', 6);
    }

    public function test_failed_stock_movements_roll_back_cart_cleanup_and_product_deactivation(): void
    {
        StockMovement::creating(function (): void {
            throw new \RuntimeException('Falha no histórico');
        });
        try {
            $this->postJson('/api/admin/products/'.$this->product->id.'/stock-movements', [
                'type' => 'OUTBOUND', 'quantity' => 2, 'reason' => 'Últimas unidades',
            ])->assertStatus(500);
        } finally {
            StockMovement::flushEventListeners();
        }
        $this->assertSame(2, $this->product->fresh()->stock);
        $this->assertTrue($this->product->fresh()->is_active);
        $this->assertDatabaseCount('cart_items', 6);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_invalid_product_changes_leave_all_cart_items_untouched(): void
    {
        $this->patchJson('/api/admin/products/'.$this->product->id, ['is_active' => false, 'price' => -1])->assertUnprocessable();
        $this->assertDatabaseCount('cart_items', 6);
        $this->assertTrue($this->product->fresh()->is_active);
    }

    public function test_legacy_cleanup_migration_remains_repeatable(): void
    {
        $this->product->update(['is_active' => false]);
        $migration = require database_path('migrations/2026_11_05_141358_remove_unavailable_products_from_carts.php');
        $migration->up();
        $migration->up();
        $this->assertDatabaseMissing('cart_items', ['product_id' => $this->product->id]);
        $this->assertDatabaseCount('cart_items', 3);
    }
}
