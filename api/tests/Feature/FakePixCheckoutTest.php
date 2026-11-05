<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Category;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FakePixCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private Product $product;

    private int $addressId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::create(['name' => 'Cliente Fake Pix', 'email' => 'fakepix@example.com', 'password' => 'SenhaCliente!2026']);
        $category = Category::create(['name' => 'Componentes']);
        $this->product = Product::create(['category_id' => $category->id, 'name' => 'Mouse', 'price' => '19.90', 'cost' => '10.00', 'stock' => 3])->refresh();
        $this->addressId = $this->customer->addresses()->create([
            'postal_code' => '01001-000', 'street' => 'Rua de Entrega', 'number' => '42', 'city' => 'São Paulo', 'state' => 'SP',
        ])->id;
        $this->actingAs($this->customer, 'web');
        $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 2])->assertOk();
    }

    /** @return array{address_id: int, payment_method: string, checkout_token: string, expected_total: string} */
    private function checkoutData(): array
    {
        return ['address_id' => $this->addressId, 'payment_method' => 'FAKE_PIX', 'checkout_token' => (string) Str::uuid(), 'expected_total' => '69.80'];
    }

    public function test_fake_pix_creates_a_paid_order_with_server_prices_shipping_and_address_and_clears_the_cart(): void
    {
        $this->getJson('/api/cart')->assertOk()->assertJsonPath('data.shipping_total', '30.00')->assertJsonPath('data.total', '69.80');
        $id = $this->postJson('/api/checkout', $this->checkoutData())->assertCreated()
            ->assertJsonPath('data.status', 'PAID')->assertJsonPath('data.payment_method', 'FAKE_PIX')
            ->assertJsonPath('data.products_total', '39.80')->assertJsonPath('data.shipping_total', '30.00')
            ->assertJsonPath('data.total', '69.80')->assertJsonPath('data.shipping_address.street', 'Rua de Entrega')
            ->assertJsonPath('data.items.0.unit_price', '19.90')->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonMissingPath('data.items.0.unit_cost')->assertJsonMissingPath('data.checkout_token')->json('data.id');

        $this->assertDatabaseHas('orders', ['id' => $id, 'user_id' => $this->customer->id, 'payment_method' => 'FAKE_PIX', 'status' => 'PAID']);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $this->product->id, 'type' => 'OUTBOUND', 'quantity' => 2]);
        $this->assertSame(1, $this->product->fresh()->stock);
        $this->assertDatabaseCount('order_status_histories', 2);
        $this->getJson('/api/cart')->assertOk()->assertJsonCount(0, 'data.items')->assertJsonPath('data.total', '0.00')->assertJsonPath('data.shipping_total', '0.00');
        $this->getJson('/api/orders')->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.status', 'PAID');
        $this->customer->addresses()->findOrFail($this->addressId)->update(['street' => 'Rua Alterada']);
        $this->getJson('/api/orders/'.$id)->assertOk()->assertJsonPath('data.shipping_address.street', 'Rua de Entrega');
    }

    public function test_retrying_the_same_attempt_returns_the_order_without_reserving_stock_or_clearing_new_items(): void
    {
        $data = $this->checkoutData();
        $id = $this->postJson('/api/checkout', $data)->assertCreated()->json('data.id');
        $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 1])->assertOk();
        $this->postJson('/api/checkout', $data)->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseCount('order_status_histories', 2);
        $this->assertSame(1, $this->product->fresh()->stock);
        $this->getJson('/api/cart')->assertOk()->assertJsonPath('data.items.0.quantity', 1);
    }

    public function test_buying_the_last_units_deactivates_the_product_and_a_retry_still_succeeds(): void
    {
        $this->product->update(['stock' => 2]);
        $data = $this->checkoutData();
        $id = $this->postJson('/api/checkout', $data)->assertCreated()->json('data.id');
        $this->assertSame(0, $this->product->fresh()->stock);
        $this->assertFalse($this->product->fresh()->is_active);
        $this->postJson('/api/checkout', $data)->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_another_customers_address_cannot_be_used_and_failed_checkout_preserves_cart_and_stock(): void
    {
        $other = User::create(['name' => 'Outro', 'email' => 'otherfakepix@example.com', 'password' => 'SenhaCliente!2026']);
        $address = $other->addresses()->create(['postal_code' => '01001-000', 'street' => 'Rua Alheia', 'number' => '1', 'city' => 'São Paulo', 'state' => 'SP']);
        $this->postJson('/api/checkout', array_replace($this->checkoutData(), ['address_id' => $address->id]))->assertNotFound();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(3, $this->product->fresh()->stock);
        $this->getJson('/api/cart')->assertOk()->assertJsonPath('data.items.0.quantity', 2);
    }

    public function test_price_changes_require_review_and_roll_back_order_and_stock(): void
    {
        $data = $this->checkoutData();
        $this->product->update(['price' => '29.90']);
        $this->postJson('/api/checkout', $data)->assertConflict();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('order_status_histories', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(3, $this->product->fresh()->stock);
        $this->getJson('/api/cart')->assertOk()->assertJsonPath('data.total', '89.80')->assertJsonPath('data.items.0.quantity', 2);
    }

    public function test_unavailable_and_insufficient_stock_prevent_payment_and_preserve_cart(): void
    {
        foreach ([['stock' => 1], ['stock' => 3, 'is_active' => false]] as $attributes) {
            $this->product->update($attributes);
            $this->postJson('/api/checkout', $this->checkoutData())->assertUnprocessable();
            $this->assertDatabaseCount('orders', 0);
            $this->assertDatabaseCount('stock_movements', 0);
            $this->assertDatabaseCount('cart_items', 1);
        }
    }

    public function test_a_failed_fake_payment_rolls_back_order_history_stock_and_cart_removal(): void
    {
        OrderStatusHistory::creating(function (OrderStatusHistory $history): void {
            if ($history->status === 'PAID') {
                throw new \RuntimeException('Falha ao registrar pagamento');
            }
        });
        try {
            $this->postJson('/api/checkout', $this->checkoutData())->assertStatus(500);
        } finally {
            OrderStatusHistory::flushEventListeners();
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('order_status_histories', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(3, $this->product->fresh()->stock);
        $this->assertDatabaseCount('cart_items', 1);
    }

    public function test_checkout_requires_authentication_fake_pix_valid_attempt_and_server_owned_fields(): void
    {
        $this->postJson('/api/checkout', [])->assertUnprocessable()->assertJsonValidationErrors(['address_id', 'payment_method', 'checkout_token', 'expected_total']);
        foreach ([
            ['payment_method' => 'PIX'], ['checkout_token' => 'invalid'], ['address_id' => 0], ['expected_total' => '-1'],
            ['expected_total' => '1.001'], ['total' => '0.01'], ['user_id' => 999], ['status' => 'PAID'],
            ['items' => [['product_id' => $this->product->id, 'quantity' => 1]]], ['card_number' => '4111111111111111'],
        ] as $invalid) {
            $this->postJson('/api/checkout', array_replace($this->checkoutData(), $invalid))->assertUnprocessable()->assertJsonValidationErrors(array_keys($invalid));
        }
        $this->app['auth']->guard('web')->logout();
        $this->postJson('/api/checkout', $this->checkoutData())->assertUnauthorized();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_empty_carts_cannot_create_paid_orders(): void
    {
        Cart::where('user_id', $this->customer->id)->firstOrFail()->items()->delete();
        $this->postJson('/api/checkout', $this->checkoutData())->assertUnprocessable()->assertJsonValidationErrors('cart');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_tokens_are_scoped_to_the_customer(): void
    {
        $data = $this->checkoutData();
        $id = $this->postJson('/api/checkout', $data)->assertCreated()->json('data.id');
        $other = User::create(['name' => 'Outro', 'email' => 'retryfakepix@example.com', 'password' => 'SenhaCliente!2026']);
        $this->actingAs($other, 'web')->postJson('/api/checkout', $data)->assertUnprocessable()->assertJsonValidationErrors('cart');
        $this->getJson('/api/orders/'.$id)->assertNotFound();
        $this->assertDatabaseCount('orders', 1);
    }
}
