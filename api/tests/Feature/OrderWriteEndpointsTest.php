<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderWriteEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private User $other;

    private User $admin;

    private Product $product;

    private int $addressId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::create(['name' => 'Cliente', 'email' => 'customer@example.com', 'password' => 'SenhaCliente!2026'])->refresh();
        $this->other = User::create(['name' => 'Outro', 'email' => 'other@example.com', 'password' => 'SenhaCliente!2026'])->refresh();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'SenhaAdmin!2026'])->refresh();
        $this->admin->forceFill(['role' => 'ADMIN'])->save();
        $this->addressId = $this->customer->addresses()->create(['postal_code' => '01001-000', 'street' => 'Rua Original', 'number' => '10', 'city' => 'São Paulo', 'state' => 'SP'])->id;
        $category = Category::create(['name' => 'Peças']);
        $this->product = Product::create(['category_id' => $category->id, 'name' => 'Produto Original', 'price' => '0.10', 'cost' => '0.05', 'stock' => 10])->refresh();
        $this->actingAs($this->customer, 'web');
    }

    /** @return array<string, mixed> */
    private function checkoutData(int $quantity = 3): array
    {
        return ['address_id' => $this->addressId, 'payment_method' => 'PIX', 'items' => [['product_id' => $this->product->id, 'quantity' => $quantity]]];
    }

    public function test_checkout_uses_server_prices_shipping_and_snapshots_and_decrements_stock(): void
    {
        config(['shop.shipping_total' => '5.90']);
        $response = $this->postJson('/api/order', $this->checkoutData())->assertCreated()
            ->assertJsonPath('data.status', 'PENDING')->assertJsonPath('data.products_total', '0.30')
            ->assertJsonPath('data.shipping_total', '5.90')->assertJsonPath('data.total', '6.20')
            ->assertJsonPath('data.items.0.line_total', '0.30')->assertJsonPath('data.items_quantity', 3)
            ->assertJsonPath('data.shipping_address.street', 'Rua Original')
            ->assertJsonPath('data.timeline.0.status', 'PENDING')->assertJsonMissingPath('data.items.0.unit_cost');
        $id = $response->json('data.id');
        $this->assertDatabaseHas('products', ['id' => $this->product->id, 'stock' => 7]);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $this->product->id, 'type' => 'OUTBOUND', 'change' => -3, 'quantity' => 3]);
        $this->assertDatabaseHas('order_items', ['order_id' => $id, 'unit_cost' => '0.05']);
        $this->product->update(['price' => '999.99', 'name' => 'Nome alterado']);
        $this->customer->addresses()->findOrFail($this->addressId)->update(['street' => 'Rua Nova']);
        $this->getJson('/api/order/'.$id)->assertOk()->assertJsonPath('data.items.0.product_name_snapshot', 'Produto Original')
            ->assertJsonPath('data.items.0.unit_price', '0.10')->assertJsonPath('data.shipping_address.street', 'Rua Original');
    }

    public function test_checkout_rejects_client_totals_status_and_duplicate_products(): void
    {
        foreach (['total' => '0.01', 'shipping_total' => '0.00', 'products_total' => '0.01', 'status' => 'PAID', 'user_id' => $this->other->id] as $field => $value) {
            $this->postJson('/api/order', $this->checkoutData() + [$field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $data = $this->checkoutData();
        $data['items'][] = $data['items'][0];
        $this->postJson('/api/order', $data)->assertUnprocessable();
        $data = $this->checkoutData();
        $data['items'][0]['unit_price'] = '0.01';
        $this->postJson('/api/order', $data)->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $this->product->fresh()->stock);
    }

    public function test_checkout_cannot_use_another_customers_address(): void
    {
        $this->actingAs($this->other, 'web')->postJson('/api/order', $this->checkoutData())->assertNotFound();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_refuses_insufficient_stock_unavailable_products_and_zero_quantities(): void
    {
        $this->postJson('/api/order', $this->checkoutData(11))->assertUnprocessable()->assertJsonValidationErrors('items.0.quantity');
        $this->postJson('/api/order', $this->checkoutData(0))->assertUnprocessable();
        $this->product->update(['is_active' => false]);
        $this->postJson('/api/order', $this->checkoutData())->assertUnprocessable();
        $this->product->update(['is_active' => true]);
        $this->product->category()->update(['is_active' => false]);
        $this->postJson('/api/order', $this->checkoutData())->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_a_later_purchase_cannot_resell_stock_already_consumed(): void
    {
        $this->postJson('/api/order', $this->checkoutData(8))->assertCreated();
        $this->postJson('/api/order', $this->checkoutData(3))->assertUnprocessable();
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(2, $this->product->fresh()->stock);
    }

    public function test_order_stock_items_snapshot_and_timeline_rollback_on_a_late_failure(): void
    {
        $second = Product::create(['category_id' => $this->product->category_id, 'name' => 'Segundo', 'price' => '10.00', 'stock' => 10]);
        $data = $this->checkoutData();
        $data['items'][] = ['product_id' => $second->id, 'quantity' => 2];
        $writes = 0;
        StockMovement::creating(function () use (&$writes): void {
            if (++$writes === 2) {
                throw new \RuntimeException('Stock journal unavailable');
            }
        });
        try {
            $this->postJson('/api/order', $data)->assertStatus(500);
        } finally {
            StockMovement::flushEventListeners();
        }
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertSame(10, $second->fresh()->stock);
        foreach (['orders', 'order_items', 'order_shipping_addresses', 'order_status_histories', 'stock_movements'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_customer_cancellation_is_idempotent_preserves_history_and_restores_stock_once(): void
    {
        $id = $this->postJson('/api/order', $this->checkoutData())->assertCreated()->json('data.id');
        $this->actingAs($this->other, 'web')->deleteJson('/api/order/'.$id)->assertNotFound();
        $this->actingAs($this->customer, 'web')->deleteJson('/api/order/'.$id)->assertNoContent();
        $this->deleteJson('/api/order/'.$id)->assertNoContent();
        $this->getJson('/api/order/'.$id)->assertOk()->assertJsonPath('data.status', 'CANCELLED')->assertJsonCount(2, 'data.timeline');
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertDatabaseCount('order_items', 1);
    }

    public function test_admin_status_transitions_are_ordered_and_repeated_status_has_no_duplicate_history(): void
    {
        $id = $this->postJson('/api/order', $this->checkoutData())->assertCreated()->json('data.id');
        $uri = '/api/admin/order/'.$id;
        $this->patchJson($uri, ['status' => 'PAID'])->assertForbidden();
        $this->actingAs($this->admin, 'web');
        $this->getJson('/api/admin/order')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson($uri)->assertOk()->assertJsonPath('data.user_id', $this->customer->id);
        $this->patchJson($uri, ['status' => 'SHIPPED'])->assertConflict();
        $this->patchJson($uri, ['status' => 'PAID'])->assertOk();
        $this->patchJson($uri, ['status' => 'PAID'])->assertOk()->assertJsonCount(2, 'data.timeline');
        $this->actingAs($this->customer, 'web')->deleteJson('/api/order/'.$id)->assertConflict();
        $this->actingAs($this->admin, 'web');
        foreach (['PROCESSING', 'SHIPPED', 'DELIVERED'] as $status) {
            $this->patchJson($uri, ['status' => $status])->assertOk()->assertJsonPath('data.status', $status);
        }
        $this->patchJson($uri, ['status' => 'CANCELLED'])->assertConflict();
        $this->assertSame(7, $this->product->fresh()->stock);
        $this->assertDatabaseCount('order_status_histories', 5);
    }

    public function test_admin_can_cancel_a_paid_order_and_stock_is_only_restored_once(): void
    {
        $id = $this->postJson('/api/order', $this->checkoutData())->assertCreated()->json('data.id');
        $this->actingAs($this->admin, 'web');
        $uri = '/api/admin/order/'.$id;
        $this->patchJson($uri, ['status' => 'PAID'])->assertOk();
        $this->patchJson($uri, ['status' => 'CANCELLED'])->assertOk();
        $this->patchJson($uri, ['status' => 'CANCELLED'])->assertOk();
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_total_overflow_does_not_create_partial_orders(): void
    {
        $this->product->update(['price' => '99999999.99']);
        $this->postJson('/api/order', $this->checkoutData(2))->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $this->product->fresh()->stock);
    }

    public function test_accounts_with_purchases_cannot_be_deleted(): void
    {
        $this->postJson('/api/order', $this->checkoutData())->assertCreated();
        $this->deleteJson('/api/user', ['current_password' => 'SenhaCliente!2026'])->assertConflict();
        $this->assertDatabaseHas('users', ['id' => $this->customer->id]);
    }
}
