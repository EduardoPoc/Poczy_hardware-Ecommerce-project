<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\AddressService;
use App\Services\OrderService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivateGetEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private User $other;

    private Order $order;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::create(['name' => 'Mariana', 'email' => 'mariana@example.com', 'password' => 'SenhaTeste!2026']);
        $this->other = User::create(['name' => 'Rafael', 'email' => 'rafael@example.com', 'password' => 'SenhaTeste!2026']);
        $category = Category::create(['name' => 'Periféricos']);
        $this->product = Product::create(['category_id' => $category->id, 'name' => 'Teclado', 'price' => '249.90', 'stock' => 20]);
        $this->order = $this->customer->orders()->create(['status' => 'PAID', 'products_total' => '499.80', 'shipping_total' => '0.00', 'total' => '499.80']);
        $this->order->items()->create(['product_id' => $this->product->id, 'category_id' => $category->id, 'product_name_snapshot' => 'Teclado', 'category_name_snapshot' => 'Periféricos', 'quantity' => 2, 'unit_price' => '249.90']);
        $this->order->shippingAddress()->create(['postal_code' => '80010-000', 'street' => 'Rua Original', 'number' => '120', 'city' => 'Curitiba', 'state' => 'PR']);
        foreach (['PENDING', 'PAID'] as $index => $status) {
            $this->order->statusHistory()->create(['status' => $status, 'changed_at' => '2026-01-15 10:0'.$index.':00']);
        }
    }

    public function test_order_lists_are_scoped_to_the_customer_and_count_units_and_lines(): void
    {
        $orders = app(OrderService::class);
        $this->assertSame(1, $orders->listUserOrders($this->customer)->total());
        $this->assertSame(0, $orders->listUserOrders($this->other)->total());
        $this->assertSame(1, $orders->listAll()->total());
        $summary = $orders->listUserOrders($this->customer)->first();
        $this->assertSame(2, (int) $summary->items_quantity);
        $this->assertSame(1, $summary->items_count);
        $this->expectException(ModelNotFoundException::class);
        $orders->getUserOrder($this->other, (string) $this->order->id);
    }

    public function test_order_detail_reads_snapshots_and_orders_history_without_writing_data(): void
    {
        $before = $this->order->fresh()->getAttributes();
        $this->product->update(['name' => 'Novo nome', 'price' => '999.00']);
        $this->product->delete();
        $detail = app(OrderService::class)->getUserOrder($this->customer, (string) $this->order->id);
        $this->assertSame(2, $detail->items_quantity);
        $this->assertSame('499.80', $detail->items->first()->line_total);
        $this->assertSame('Teclado', $detail->items->first()->product_name_snapshot);
        $this->assertNull($detail->items->first()->product_id);
        $this->assertSame('Rua Original', $detail->shippingAddress->street);
        $this->assertSame(['PENDING', 'PAID'], $detail->statusHistory->pluck('status')->all());
        $this->assertSame($before, $this->order->fresh()->getAttributes());
    }

    public function test_empty_order_has_zero_counts_and_empty_relationships(): void
    {
        $empty = $this->customer->orders()->create(['status' => 'PENDING', 'products_total' => '0.00', 'shipping_total' => '0.00', 'total' => '0.00']);
        $detail = app(OrderService::class)->findById($empty->id);
        $this->assertSame(0, $detail->items_quantity);
        $this->assertSame(0, $detail->items_count);
        $this->assertCount(0, $detail->items);
        $this->assertCount(0, $detail->statusHistory);
        $this->assertNull($detail->shippingAddress);
    }

    public function test_address_lists_only_include_their_owner(): void
    {
        $address = $this->customer->addresses()->create(['postal_code' => '80010-000', 'street' => 'Rua Pessoal', 'number' => '15', 'city' => 'Curitiba', 'state' => 'PR']);
        $addresses = app(AddressService::class);
        $this->assertCount(1, $addresses->listUserAddresses($this->customer));
        $this->assertCount(0, $addresses->listUserAddresses($this->other));
        $this->expectException(ModelNotFoundException::class);
        $addresses->getUserAddress($this->other, $address->id);
    }
}
