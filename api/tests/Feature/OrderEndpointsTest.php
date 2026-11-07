<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderEndpointsTest extends TestCase
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
        $this->addressId = $this->customer->addresses()->create([
            'postal_code' => '01001-000', 'street' => 'Rua Original', 'number' => '10', 'city' => 'São Paulo', 'state' => 'SP',
        ])->id;
        $category = Category::create(['name' => 'Peças']);
        $this->product = Product::create([
            'category_id' => $category->id, 'name' => 'Produto Original', 'price' => '0.10', 'cost' => '0.05', 'stock' => 10,
        ])->refresh();
        $this->actingAs($this->customer, 'web');
    }

    /** @return array{address_id: int, payment_method: string, items: list<array{product_id: int, quantity: int}>} */
    private function checkoutData(): array
    {
        return [
            'address_id' => $this->addressId, 'payment_method' => 'PIX',
            'items' => [['product_id' => $this->product->id, 'quantity' => 3]],
        ];
    }

    private function createOrder(): Order
    {
        return app(OrderService::class)->create($this->customer, $this->checkoutData());
    }

    public function test_checkout_freezes_server_prices_and_names_and_hides_internal_costs(): void
    {
        config(['shop.shipping_total' => '5.90']);
        $id = $this->postJson('/api/orders', $this->checkoutData())->assertCreated()
            ->assertJsonPath('data.products_total', '0.30')->assertJsonPath('data.total', '6.20')
            ->assertJsonPath('data.items.0.product_name_snapshot', 'Produto Original')
            ->assertJsonPath('data.items.0.unit_price', '0.10')->assertJsonMissingPath('data.items.0.unit_cost')
            ->json('data.id');
        $this->assertDatabaseHas('products', ['id' => $this->product->id, 'stock' => 7]);
        $this->product->update(['price' => '999.99', 'name' => 'Nome Novo']);
        $this->getJson('/api/orders/'.$id)->assertOk()->assertJsonPath('data.total', '6.20')
            ->assertJsonPath('data.items.0.product_name_snapshot', 'Produto Original')->assertJsonPath('data.items.0.unit_price', '0.10');
        $this->actingAs($this->admin, 'web')->getJson('/api/admin/orders/'.$id)->assertOk()
            ->assertJsonPath('data.items.0.unit_cost', '0.05');
    }

    public function test_checkout_validates_items_quantities_and_server_owned_fields(): void
    {
        $this->postJson('/api/orders', [])->assertUnprocessable()->assertJsonValidationErrors(['address_id', 'payment_method', 'items']);
        foreach ([
            ['items' => []],
            ['items' => [['product_id' => $this->product->id, 'quantity' => 0]]],
            ['items' => [['product_id' => $this->product->id, 'quantity' => -1]]],
            ['items' => [['product_id' => $this->product->id, 'quantity' => 1.5]]],
            ['items' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => '0.01']]],
            ['items' => [['product_id' => $this->product->id, 'quantity' => 1], ['product_id' => $this->product->id, 'quantity' => 2]]],
            ['total' => '0.01'], ['user_id' => $this->other->id], ['status' => 'PAID'],
        ] as $invalid) {
            $this->postJson('/api/orders', array_replace($this->checkoutData(), $invalid))->assertUnprocessable();
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(10, $this->product->fresh()->stock);
    }

    public function test_unavailable_or_insufficient_stock_items_prevent_the_entire_checkout(): void
    {
        $category = Category::create(['name' => 'Outras peças']);
        $second = Product::create(['category_id' => $category->id, 'name' => 'Segundo', 'price' => '1.00', 'stock' => 1]);
        $data = $this->checkoutData();
        $data['items'][] = ['product_id' => $second->id, 'quantity' => 2];
        $this->postJson('/api/orders', $data)->assertUnprocessable()->assertJsonValidationErrors('items.1.quantity');
        $second->update(['is_active' => false]);
        $data['items'][1]['quantity'] = 1;
        $this->postJson('/api/orders', $data)->assertUnprocessable()->assertJsonValidationErrors('items.1.product_id');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(10, $this->product->fresh()->stock);
    }

    public function test_only_the_owner_or_admin_can_view_pay_or_cancel_an_order(): void
    {
        $order = $this->createOrder();
        $this->actingAs($this->other, 'web')->getJson('/api/orders/'.$order->id)->assertNotFound();
        $this->postJson('/api/orders/'.$order->id.'/pay')->assertForbidden();
        $this->deleteJson('/api/orders/'.$order->id)->assertNotFound();
        $this->assertSame('PENDING', $order->fresh()->status);
        $this->actingAs($this->admin, 'web')->getJson('/api/orders/'.$order->id)->assertOk()->assertJsonPath('data.id', $order->id);
        $this->postJson('/api/orders/'.$order->id.'/pay')->assertForbidden();
    }

    public function test_owner_payment_is_simulated_and_repeated_payment_does_not_duplicate_history(): void
    {
        $order = $this->createOrder();
        $this->postJson('/api/orders/'.$order->id.'/pay', ['card_number' => '4111111111111111'])
            ->assertUnprocessable()->assertJsonValidationErrors('payment');
        $this->assertSame('PENDING', $order->fresh()->status);
        $this->postJson('/api/orders/'.$order->id.'/pay')->assertOk()->assertJsonPath('data.status', 'PAID');
        $this->postJson('/api/orders/'.$order->id.'/pay')->assertConflict();
        $this->assertDatabaseCount('order_status_histories', 2);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertSame(7, $this->product->fresh()->stock);
        $this->patchJson('/api/admin/orders/'.$order->id, ['status' => 'SHIPPED'])->assertForbidden();
    }

    public function test_paid_orders_cannot_be_cancelled_by_customer_or_administrator(): void
    {
        $order = $this->createOrder();
        $this->postJson('/api/orders/'.$order->id.'/pay')->assertOk();
        $this->deleteJson('/api/orders/'.$order->id)->assertConflict();
        $this->actingAs($this->admin, 'web')->patchJson('/api/admin/orders/'.$order->id, ['status' => 'CANCELLED'])->assertConflict();
        $this->assertSame('PAID', $order->fresh()->status);
        $this->assertSame(7, $this->product->fresh()->stock);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_admin_can_cancel_pending_orders_and_restore_stock_only_once(): void
    {
        $order = $this->createOrder();
        $this->actingAs($this->admin, 'web')->deleteJson('/api/orders/'.$order->id)->assertNoContent();
        $this->deleteJson('/api/orders/'.$order->id)->assertNoContent();
        $this->assertSame('CANCELLED', $order->fresh()->status);
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertDatabaseCount('order_status_histories', 2);
        $this->postJson('/api/orders/'.$order->id.'/pay')->assertConflict();
    }

    public function test_administrative_transitions_follow_the_expected_flow_and_reject_invalid_inputs(): void
    {
        $order = $this->createOrder();
        $this->actingAs($this->admin, 'web');
        $url = '/api/admin/orders/'.$order->id;
        $this->patchJson($url, [])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->patchJson($url, ['status' => ['PAID']])->assertUnprocessable()->assertJsonValidationErrors('status');
        foreach (['SHIPPED', 'PROCESSING', 'unknown'] as $status) {
            $this->patchJson($url, ['status' => $status])->assertConflict()->assertJsonStructure(['message']);
        }
        foreach (['PAID', 'PAID', 'SHIPPED', 'DELIVERED'] as $status) {
            $this->patchJson($url, ['status' => $status])->assertOk()->assertJsonPath('data.status', $status);
        }
        $this->postJson('/api/orders/'.$order->id.'/pay')->assertConflict();
        $this->assertDatabaseCount('order_status_histories', 4);
        $this->assertSame(7, $this->product->fresh()->stock);
    }

    public function test_order_lists_validate_pagination(): void
    {
        $this->getJson('/api/orders?per_page=0')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->actingAs($this->admin, 'web')->getJson('/api/admin/orders?per_page=101')
            ->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }
}
