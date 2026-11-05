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

    private function assertProductRemovedFromAllCarts(): void
    {
        $this->assertDatabaseMissing('cart_items', ['product_id' => $this->product->id]);
        $this->assertDatabaseCount('cart_items', 3);
        $this->assertDatabaseCount('carts', 3);
    }

    public function test_deactivating_a_product_removes_it_from_customer_and_guest_carts_and_recalculates_totals(): void
    {
        $this->patchJson('/api/admin/products/'.$this->product->id, ['is_active' => false])->assertOk();
        $this->assertProductRemovedFromAllCarts();
        $this->assertSame(2, $this->product->fresh()->stock);
        $this->actingAs($this->customer, 'web')->getJson('/api/cart')->assertOk()
            ->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.product_id', $this->otherProduct->id)
            ->assertJsonPath('data.products_total', '9.90')->assertJsonPath('data.shipping_total', '30.00')
            ->assertJsonPath('data.total', '39.90');
    }

    public function test_stock_depletion_removes_the_product_and_replenishment_does_not_restore_cart_items(): void
    {
        $url = '/api/admin/products/'.$this->product->id;
        $this->postJson($url.'/stock-movements', ['type' => 'OUTBOUND', 'quantity' => 2, 'reason' => 'Últimas unidades'])->assertCreated();
        $this->assertProductRemovedFromAllCarts();
        $this->assertFalse($this->product->fresh()->is_active);
        $this->postJson($url.'/stock-movements', ['type' => 'INBOUND', 'quantity' => 2, 'reason' => 'Reposição'])->assertCreated();
        $this->patchJson($url, ['is_active' => true])->assertOk();
        $this->assertProductRemovedFromAllCarts();
    }

    public function test_buying_the_last_unit_cleans_other_carts_and_preserves_unrelated_products(): void
    {
        $this->product->update(['stock' => 1]);
        $address = $this->customer->addresses()->create(['postal_code' => '01001-000', 'street' => 'Rua de Entrega', 'number' => '1', 'city' => 'São Paulo', 'state' => 'SP']);
        $this->actingAs($this->customer, 'web')->postJson('/api/checkout', [
            'address_id' => $address->id, 'payment_method' => 'FAKE_PIX',
            'checkout_token' => (string) Str::uuid(), 'expected_total' => '59.80',
        ])->assertCreated()->assertJsonPath('data.status', 'PAID');
        $this->assertDatabaseMissing('cart_items', ['product_id' => $this->product->id]);
        $this->assertDatabaseCount('cart_items', 2);
        $this->assertSame(0, $this->product->fresh()->stock);
        $this->getJson('/api/cart')->assertOk()->assertJsonCount(0, 'data.items')->assertJsonPath('data.total', '0.00');
    }

    public function test_category_deactivation_also_removes_its_products_from_all_carts(): void
    {
        $this->patchJson('/api/admin/categories/'.$this->product->category_id, ['is_active' => false])->assertOk();
        $this->assertProductRemovedFromAllCarts();
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

    public function test_old_unavailable_items_are_hidden_and_the_upgrade_removes_them_without_losing_other_items(): void
    {
        $this->product->update(['is_active' => false]);
        $this->actingAs($this->customer, 'web')->getJson('/api/cart')->assertOk()
            ->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.product_id', $this->otherProduct->id);
        $migration = require database_path('migrations/2026_11_05_141358_remove_unavailable_products_from_carts.php');
        $migration->up();
        $this->assertProductRemovedFromAllCarts();
    }
}
