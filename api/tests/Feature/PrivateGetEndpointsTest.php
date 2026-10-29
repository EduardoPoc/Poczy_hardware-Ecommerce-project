<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class PrivateGetEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private User $otherCustomer;

    private Order $order;

    private Order $otherOrder;

    private Product $keyboard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::create(['name' => 'Mariana Costa', 'email' => 'mariana@example.com', 'password' => 'SenhaTeste!2026'])->fresh();
        $this->otherCustomer = User::create(['name' => 'Rafael Souza', 'email' => 'rafael@example.com', 'password' => 'OutraSenha!2026'])->fresh();
        $this->customer->addresses()->create(['postal_code' => '80010-000', 'street' => 'Rua do Cliente', 'number' => '120', 'city' => 'Curitiba', 'state' => 'PR']);
        $this->otherCustomer->addresses()->create(['postal_code' => '01001-000', 'street' => 'Rua de Outro Cliente', 'number' => '200', 'city' => 'São Paulo', 'state' => 'SP']);
        $category = Category::create(['name' => 'Periféricos']);
        $this->keyboard = Product::create(['category_id' => $category->id, 'name' => 'Teclado Redragon', 'price' => '249.90', 'cost' => '175.00', 'stock' => 20]);
        $this->order = $this->customer->orders()->create(['status' => 'PAID', 'payment_method' => 'PIX', 'products_total' => '859.50', 'shipping_total' => '20.00', 'total' => '879.50']);
        $this->otherOrder = $this->otherCustomer->orders()->create(['status' => 'PENDING', 'products_total' => '100.00', 'shipping_total' => '0.00', 'total' => '100.00']);
        $this->order->items()->create(['product_id' => $this->keyboard->id, 'category_id' => $category->id, 'product_name_snapshot' => 'Teclado Redragon', 'category_name_snapshot' => 'Periféricos', 'quantity' => 2, 'unit_price' => '249.90', 'unit_cost' => '175.00']);
        $this->order->items()->create(['product_name_snapshot' => 'Mouse Logitech', 'category_name_snapshot' => 'Periféricos', 'quantity' => 3, 'unit_price' => '119.90', 'unit_cost' => '78.00']);
        $this->order->shippingAddress()->create(['postal_code' => '80010-000', 'street' => 'Rua da Entrega Original', 'number' => '120', 'city' => 'Curitiba', 'state' => 'PR']);
        $this->order->statusHistory()->create(['status' => 'PAID', 'changed_at' => '2026-01-15 12:00:00']);
        $this->order->statusHistory()->create(['status' => 'PENDING', 'changed_at' => '2026-01-15 10:00:00']);
    }

    public function test_all_personal_endpoints_require_authentication(): void
    {
        foreach (['/api/address', '/api/user', '/api/order', '/api/order/'.$this->order->id] as $path) {
            $this->getJson($path)->assertUnauthorized();
        }
        $this->withToken('not-a-session')->getJson('/api/user')->assertUnauthorized();
    }

    public function test_addresses_and_profile_only_expose_the_authenticated_customer(): void
    {
        $this->actingAs($this->customer, 'web');
        $this->getJson('/api/address?user_id='.$this->otherCustomer->id)->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.street', 'Rua do Cliente')
            ->assertJsonMissingPath('data.0.user_id');
        $this->customer->setAttribute('remember_token', 'hidden-token');
        $this->getJson('/api/user?user_id='.$this->otherCustomer->id)->assertOk()
            ->assertJsonPath('data.id', $this->customer->id)
            ->assertJsonPath('data.email', 'mariana@example.com')
            ->assertJsonPath('data.role', 'CUSTOMER')
            ->assertJsonMissingPath('data.password')->assertJsonMissingPath('data.remember_token')
            ->assertJsonMissingPath('data.password_changes')->assertJsonMissingPath('data.orders');
    }

    public function test_order_summary_counts_units_and_lines_without_loading_details_or_other_customers_orders(): void
    {
        $this->actingAs($this->customer, 'web');
        $this->getJson('/api/order?user_id='.$this->otherCustomer->id)->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $this->order->id)
            ->assertJsonPath('data.0.items_quantity', 5)->assertJsonPath('data.0.items_count', 2)
            ->assertJsonPath('data.0.total', '879.50')
            ->assertJsonMissingPath('data.0.items')->assertJsonMissingPath('data.0.timeline');
    }

    public function test_order_detail_uses_snapshots_and_orders_the_timeline_without_writing_domain_data(): void
    {
        $beforeOrder = $this->order->fresh()->getAttributes();
        $beforeProduct = $this->keyboard->fresh()->getAttributes();
        $beforeItems = $this->order->items()->get()->map->getAttributes()->all();
        $this->actingAs($this->customer, 'web');
        $this->getJson('/api/order/'.$this->order->id)->assertOk()
            ->assertJsonPath('data.items_quantity', 5)->assertJsonPath('data.items_count', 2)
            ->assertJsonPath('data.items.0.line_total', '499.80')
            ->assertJsonPath('data.items.1.line_total', '359.70')
            ->assertJsonMissingPath('data.items.0.unit_cost')
            ->assertJsonPath('data.shipping_address.street', 'Rua da Entrega Original')
            ->assertJsonPath('data.timeline.0.status', 'PENDING')->assertJsonPath('data.timeline.1.status', 'PAID');
        $this->assertSame($beforeOrder, $this->order->fresh()->getAttributes());
        $this->assertSame($beforeProduct, $this->keyboard->fresh()->getAttributes());
        $this->assertSame($beforeItems, $this->order->items()->get()->map->getAttributes()->all());
        $this->assertSame(2, $this->order->statusHistory()->count());
    }

    public function test_purchase_snapshots_survive_product_and_address_changes_and_deleted_products(): void
    {
        $this->keyboard->update(['name' => 'Nome novo', 'price' => '999.00']);
        $this->customer->addresses()->first()->update(['street' => 'Rua Nova']);
        $this->keyboard->delete();
        $this->actingAs($this->customer, 'web');
        $this->getJson('/api/order/'.$this->order->id)->assertOk()
            ->assertJsonPath('data.items.0.product_id', null)
            ->assertJsonPath('data.items.0.product_name_snapshot', 'Teclado Redragon')
            ->assertJsonPath('data.items.0.unit_price', '249.90')
            ->assertJsonPath('data.shipping_address.street', 'Rua da Entrega Original');
    }

    public function test_orders_outside_the_customer_scope_and_missing_orders_return_404(): void
    {
        $this->actingAs($this->customer, 'web');
        $this->getJson('/api/order/'.$this->otherOrder->id)->assertNotFound();
        $this->getJson('/api/order/999999')->assertNotFound();
    }

    public function test_empty_orders_have_zero_counts_and_nullable_address_and_empty_timeline(): void
    {
        $empty = $this->customer->orders()->create(['status' => 'PENDING', 'products_total' => '0.00', 'shipping_total' => '0.00', 'total' => '0.00']);
        $this->actingAs($this->customer, 'web');
        $this->getJson('/api/order/'.$empty->id)->assertOk()
            ->assertJsonPath('data.items_quantity', 0)->assertJsonPath('data.items_count', 0)
            ->assertJsonPath('data.items', [])->assertJsonPath('data.shipping_address', null)
            ->assertJsonPath('data.timeline', []);
        $this->getJson('/api/order?per_page=1&page=1')->assertOk()->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.id', $empty->id);
        $this->getJson('/api/order?per_page=101')->assertUnprocessable();
    }

    public function test_customer_without_purchases_or_addresses_receives_empty_collections(): void
    {
        $customer = User::create(['name' => 'Ana Lima', 'email' => 'ana@example.com', 'password' => 'SenhaAna!2026']);
        $this->actingAs($customer, 'web');
        $this->getJson('/api/address')->assertOk()->assertJsonPath('data', []);
        $this->getJson('/api/order')->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 0);
    }

    public function test_session_authentication_works_without_acting_as_or_bearer_token(): void
    {
        $this->withSession([Auth::guard('web')->getName() => $this->customer->id])
            ->getJson('/api/user')->assertOk()->assertJsonPath('data.id', $this->customer->id);
    }

    public function test_allowed_frontend_origin_can_read_with_session_credentials(): void
    {
        $this->actingAs($this->customer, 'web');
        $this->getJson('/api/user', ['Origin' => 'http://localhost:5173'])->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }
}
