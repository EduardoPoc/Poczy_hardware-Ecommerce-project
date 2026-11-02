<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class OrderWriteEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private User $other;

    private Product $product;

    private int $addressId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::create(['name' => 'Cliente', 'email' => 'customer@example.com', 'password' => 'SenhaCliente!2026'])->refresh();
        $this->other = User::create(['name' => 'Outro', 'email' => 'other@example.com', 'password' => 'SenhaCliente!2026'])->refresh();
        $this->addressId = $this->customer->addresses()->create(['postal_code' => '01001-000', 'street' => 'Rua Original', 'number' => '10', 'city' => 'São Paulo', 'state' => 'SP'])->id;
        $category = Category::create(['name' => 'Peças']);
        $this->product = Product::create(['category_id' => $category->id, 'name' => 'Produto Original', 'price' => '0.10', 'cost' => '0.05', 'stock' => 10])->refresh();
    }

    /** @return array<string, mixed> */
    private function checkoutData(int $quantity = 3): array
    {
        return ['address_id' => $this->addressId, 'payment_method' => 'PIX', 'items' => [['product_id' => $this->product->id, 'quantity' => $quantity]]];
    }

    public function test_checkout_uses_decimal_prices_and_server_totals_and_preserves_snapshots(): void
    {
        config(['shop.shipping_total' => '5.90']);
        $order = app(OrderService::class)->create($this->customer, $this->checkoutData() + ['total' => '0.01', 'status' => 'PAID']);
        $this->assertSame('PENDING', $order->status);
        $this->assertSame('0.30', $order->products_total);
        $this->assertSame('5.90', $order->shipping_total);
        $this->assertSame('6.20', $order->total);
        $this->assertSame('0.30', $order->items->first()->line_total);
        $this->assertSame(3, $order->items_quantity);
        $this->assertSame('Rua Original', $order->shippingAddress->street);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $this->product->id, 'type' => 'OUTBOUND', 'quantity' => 3]);
        $this->assertSame(7, $this->product->fresh()->stock);
        $this->product->update(['price' => '999.99', 'name' => 'Nome alterado']);
        $this->customer->addresses()->findOrFail($this->addressId)->update(['street' => 'Rua Nova']);
        $snapshot = app(OrderService::class)->getUserOrder($this->customer, (string) $order->id);
        $this->assertSame('Produto Original', $snapshot->items->first()->product_name_snapshot);
        $this->assertSame('0.10', $snapshot->items->first()->unit_price);
        $this->assertSame('Rua Original', $snapshot->shippingAddress->street);
    }

    public function test_checkout_cannot_use_another_customers_address(): void
    {
        $this->expectException(ModelNotFoundException::class);
        app(OrderService::class)->create($this->other, $this->checkoutData());
    }

    public function test_insufficient_stock_does_not_create_orders_or_movements(): void
    {
        try {
            app(OrderService::class)->create($this->customer, $this->checkoutData(11));
            $this->fail('O saldo deve limitar a compra.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('orders', 0);
            $this->assertDatabaseCount('stock_movements', 0);
            $this->assertSame(10, $this->product->fresh()->stock);
        }
    }

    public function test_a_later_purchase_cannot_reuse_consumed_stock(): void
    {
        $orders = app(OrderService::class);
        $orders->create($this->customer, $this->checkoutData(8));
        try {
            $orders->create($this->customer, $this->checkoutData(3));
            $this->fail('A segunda compra excede o saldo restante.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('orders', 1);
            $this->assertSame(2, $this->product->fresh()->stock);
        }
    }

    public function test_order_stock_snapshots_and_history_roll_back_on_late_failure(): void
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
            app(OrderService::class)->create($this->customer, $data);
            $this->fail('O segundo registro de estoque deveria falhar.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Stock journal unavailable', $exception->getMessage());
        } finally {
            StockMovement::flushEventListeners();
        }
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertSame(10, $second->fresh()->stock);
        foreach (['orders', 'order_items', 'order_shipping_addresses', 'order_status_histories', 'stock_movements'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_customer_cancellation_preserves_history_and_restores_stock_only_once(): void
    {
        $orders = app(OrderService::class);
        $order = $orders->create($this->customer, $this->checkoutData());
        try {
            $orders->cancel($this->other, $order->id);
            $this->fail('Outro cliente não pode cancelar o pedido.');
        } catch (ModelNotFoundException) {
            $this->assertSame('PENDING', $order->fresh()->status);
        }
        $orders->cancel($this->customer, $order->id);
        $orders->cancel($this->customer, $order->id);
        $this->assertSame('CANCELLED', $order->fresh()->status);
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertDatabaseCount('order_items', 1);
    }

    public function test_status_transitions_are_ordered_and_repeated_updates_do_not_duplicate_history(): void
    {
        $orders = app(OrderService::class);
        $order = $orders->create($this->customer, $this->checkoutData());
        try {
            $orders->updateStatus($order->id, 'SHIPPED');
            $this->fail('Não deve pular o pagamento.');
        } catch (ConflictHttpException) {
            $this->assertSame('PENDING', $order->fresh()->status);
        }
        foreach (['PAID', 'PAID', 'SHIPPED', 'DELIVERED'] as $status) {
            $this->assertSame($status, $orders->updateStatus($order->id, $status)->status);
        }
        $this->assertDatabaseCount('order_status_histories', 4);
        $this->assertSame(7, $this->product->fresh()->stock);
    }

    public function test_total_overflow_does_not_persist_partial_orders(): void
    {
        $this->product->update(['price' => '99999999.99']);
        try {
            app(OrderService::class)->create($this->customer, $this->checkoutData(2));
            $this->fail('O total deve caber no campo decimal.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('orders', 0);
            $this->assertSame(10, $this->product->fresh()->stock);
        }
    }
}
