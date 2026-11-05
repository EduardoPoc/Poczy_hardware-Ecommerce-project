<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductBusinessRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create([
            'name' => 'Administrador',
            'email' => 'admin@example.com',
            'password' => 'SenhaAdmin!2026',
        ]);
        $this->admin->forceFill(['role' => 'ADMIN'])->save();
        $this->category = Category::create(['name' => 'Componentes']);
    }

    /** @param array<string, mixed> $attributes */
    private function product(array $attributes = []): Product
    {
        return Product::create(array_replace([
            'category_id' => $this->category->id,
            'name' => 'Memória',
            'price' => '249.90',
            'cost' => '170.00',
            'stock' => 10,
        ], $attributes))->refresh();
    }

    public function test_active_product_creation_requires_at_least_one_unit(): void
    {
        $this->actingAs($this->admin, 'web');
        $data = ['category_id' => $this->category->id, 'name' => 'Memória', 'price' => '249.90'];
        $this->postJson('/api/admin/products', $data)->assertUnprocessable()->assertJsonValidationErrors('is_active');
        $this->postJson('/api/admin/products', $data + ['stock' => 0, 'is_active' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('is_active');
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('stock_movements', 0);

        $this->postJson('/api/admin/products', $data + ['stock' => 1, 'is_active' => true])
            ->assertCreated()->assertJsonPath('data.is_active', true)->assertJsonPath('data.stock', 1);
        $this->postJson('/api/admin/products', $data + ['stock' => 0, 'is_active' => false])
            ->assertCreated()->assertJsonPath('data.is_active', false)->assertJsonPath('data.stock', 0);
    }

    public function test_empty_stock_cannot_be_activated_until_replenished(): void
    {
        $product = $this->product(['stock' => 0, 'is_active' => false]);
        $url = '/api/admin/products/'.$product->id;
        $this->actingAs($this->admin, 'web')->patchJson($url, ['is_active' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('is_active');
        $this->assertFalse($product->fresh()->is_active);

        $this->postJson($url.'/stock-movements', ['type' => 'INBOUND', 'quantity' => 1, 'reason' => 'Reposição'])
            ->assertCreated();
        $this->assertFalse($product->fresh()->is_active);
        $this->patchJson($url, ['is_active' => true])->assertOk()->assertJsonPath('data.is_active', true);
        $this->getJson('/api/products/'.$product->id)->assertOk();
    }

    public function test_outbound_and_adjustment_deactivate_products_when_stock_reaches_zero(): void
    {
        $this->actingAs($this->admin, 'web');
        foreach (['OUTBOUND' => 1, 'ADJUSTMENT' => -1] as $type => $quantity) {
            $product = $this->product(['stock' => 1]);
            $this->postJson('/api/admin/products/'.$product->id.'/stock-movements', [
                'type' => $type, 'quantity' => $quantity, 'reason' => 'Última unidade',
            ])->assertCreated();

            $this->assertSame(0, $product->fresh()->stock);
            $this->assertFalse($product->fresh()->is_active);
            $this->getJson('/api/products/'.$product->id)->assertNotFound();
        }
    }

    public function test_public_catalog_and_filters_hide_existing_active_products_without_stock(): void
    {
        $this->category->update(['attributes' => ['capacity' => ['name' => 'Capacidade', 'type' => 'integer']]]);
        $product = $this->product(['stock' => 0, 'specifications' => ['capacity' => 32]]);
        $available = $this->product(['stock' => 1, 'specifications' => ['capacity' => 16]]);

        $this->getJson('/api/products/'.$product->id)->assertNotFound();
        $this->getJson('/api/products')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $available->id);
        $this->getJson('/api/products/filter')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/catalog')->assertOk()->assertJsonCount(1, 'data.products');
        $this->getJson('/api/categories/'.$this->category->id.'/filters')->assertOk()
            ->assertJsonPath('data.attributes.capacity.values', [16]);
    }

    public function test_upgrade_deactivates_empty_stock_and_preserves_other_product_statuses(): void
    {
        $empty = $this->product(['stock' => 0]);
        $available = $this->product(['stock' => 1]);
        $inactive = $this->product(['stock' => 1, 'is_active' => false]);
        $migration = require database_path('migrations/2026_11_05_131957_deactivate_products_without_stock.php');

        $migration->up();

        $this->assertFalse($empty->fresh()->is_active);
        $this->assertTrue($available->fresh()->is_active);
        $this->assertFalse($inactive->fresh()->is_active);
    }

    public function test_sold_product_without_stock_movements_cannot_be_deleted_and_can_be_deactivated(): void
    {
        $product = $this->product();
        $order = $this->admin->orders()->create([
            'status' => 'PAID',
            'products_total' => '249.90',
            'shipping_total' => '0.00',
            'total' => '249.90',
        ]);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'category_id' => $this->category->id,
            'product_name_snapshot' => $product->name,
            'category_name_snapshot' => $this->category->name,
            'quantity' => 1,
            'unit_price' => '249.90',
        ]);
        $this->assertDatabaseCount('stock_movements', 0);

        $this->actingAs($this->admin, 'web')
            ->deleteJson('/api/admin/products/'.$product->id)
            ->assertConflict()->assertJsonStructure(['message']);

        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertSame($product->id, $item->fresh()->product_id);

        $this->patchJson('/api/admin/products/'.$product->id, ['is_active' => false])
            ->assertOk()->assertJsonPath('data.is_active', false);
        $this->getJson('/api/products/'.$product->id)->assertNotFound();
        $this->getJson('/api/products')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/admin/products/'.$product->id)->assertOk();
        $this->assertSame('Memória', $item->fresh()->product_name_snapshot);
        $this->assertSame('249.90', $item->fresh()->unit_price);
        $this->assertSame($product->id, $item->fresh()->product_id);
    }

    public function test_product_without_orders_or_stock_movements_can_be_deleted(): void
    {
        $product = $this->product(['stock' => 0]);

        $this->actingAs($this->admin, 'web')
            ->deleteJson('/api/admin/products/'.$product->id)->assertNoContent();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_filter_without_parameters_lists_active_products_and_hides_costs(): void
    {
        $product = $this->product();
        $this->product(['name' => 'Inativo', 'is_active' => false]);

        $this->getJson('/api/products/filter')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.price', '249.90')->assertJsonMissingPath('data.0.cost');
    }

    public function test_malformed_filter_inputs_return_consistent_validation_errors(): void
    {
        foreach (['invalid-json', '{', 'null', 'true', '10'] as $filter) {
            $this->getJson('/api/products/filter?'.http_build_query(['filter' => $filter]))
                ->assertUnprocessable()->assertJsonStructure(['message', 'errors'])
                ->assertJsonValidationErrors('filter');
        }

        foreach ([
            ['filter' => ['q' => ['invalid']]],
            ['filter' => ['min_price' => 10, 'max_price' => 1]],
            ['filter' => ['brand_ids' => 'invalid']],
            ['filter' => ['specifications' => ['capacity' => [[16]]]]],
            ['filter' => ['specifications' => ['invalid.key' => [16]]]],
            ['per_page' => 0],
            ['per_page' => 101],
            ['page' => 'invalid'],
        ] as $query) {
            $this->getJson('/api/products/filter?'.http_build_query($query))
                ->assertUnprocessable()->assertJsonStructure(['message', 'errors']);
        }
    }

    public function test_json_filter_preserves_types_price_limits_and_pagination_links(): void
    {
        foreach (['Memória A', 'Memória B'] as $name) {
            $this->product(['name' => $name, 'specifications' => ['capacity' => 16, 'wireless' => false]]);
        }
        $this->product(['name' => 'Memória C', 'specifications' => ['capacity' => 32, 'wireless' => true]]);
        $query = http_build_query([
            'per_page' => 1,
            'filter' => json_encode([
                'min_price' => '249.90',
                'max_price' => '249.90',
                'specifications' => ['capacity' => [16], 'wireless' => [false]],
            ]),
        ]);

        $first = $this->getJson('/api/products/filter?'.$query)->assertOk()
            ->assertJsonPath('meta.total', 2)->assertJsonMissingPath('data.0.cost');
        $second = $this->getJson($first->json('links.next'))->assertOk()
            ->assertJsonPath('meta.total', 2)->assertJsonPath('meta.current_page', 2);

        $this->assertNotSame($first->json('data.0.id'), $second->json('data.0.id'));
    }
}
