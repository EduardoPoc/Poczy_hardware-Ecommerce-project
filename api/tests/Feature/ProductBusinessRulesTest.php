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
