<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogValidationTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'SenhaAdmin!2026']);
        $admin->forceFill(['role' => 'ADMIN'])->save();
        $this->actingAs($admin, 'web');
        $this->category = Category::create(['name' => 'Componentes']);
    }

    public function test_product_creation_validates_required_fields_types_and_database_limits(): void
    {
        $this->postJson('/api/admin/products', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id', 'name', 'price']);

        $invalid = [
            'category_id' => 999999,
            'brand_id' => 999999,
            'name' => str_repeat('a', 151),
            'description' => [],
            'specifications' => 'invalid',
            'price' => '10.123',
            'cost' => -1,
            'stock' => 4294967296,
            'image_url' => 'invalid',
            'is_active' => 'invalid',
        ];
        $this->postJson('/api/admin/products', $invalid)->assertUnprocessable()
            ->assertJsonValidationErrors(array_keys($invalid));
        $this->assertDatabaseCount('products', 0);
    }

    public function test_validated_fields_only_are_forwarded_and_updates_are_partial(): void
    {
        $id = $this->postJson('/api/admin/products', [
            'category_id' => $this->category->id, 'name' => 'SSD', 'price' => '399.90',
            'cost' => '200.00', 'id' => 999999, 'created_at' => '2000-01-01', 'is_active' => false,
        ])->assertCreated()->assertJsonPath('data.stock', 0)->json('data.id');
        $this->assertNotSame(999999, $id);

        $this->patchJson('/api/admin/products/'.$id, ['name' => 'SSD atualizado'])
            ->assertOk()->assertJsonPath('data.name', 'SSD atualizado')->assertJsonPath('data.price', '399.90');
        $this->patchJson('/api/admin/products/'.$id, ['price' => null])->assertUnprocessable()->assertJsonValidationErrors('price');
        $this->patchJson('/api/admin/products/'.$id, ['price' => 100000000])->assertUnprocessable()->assertJsonValidationErrors('price');
        $this->patchJson('/api/admin/products/'.$id, ['stock' => 10])->assertUnprocessable()->assertJsonValidationErrors('stock');
        $this->assertDatabaseHas('products', ['id' => $id, 'stock' => 0]);
    }

    public function test_brand_validation_allows_keeping_its_name_and_rejects_duplicates(): void
    {
        $this->postJson('/api/admin/brands', [])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson('/api/admin/brands', ['name' => str_repeat('a', 101)])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
        $id = $this->postJson('/api/admin/brands', ['name' => 'Kingston'])->assertCreated()->json('data.id');
        $this->patchJson('/api/admin/brands/'.$id, ['name' => 'Kingston'])->assertOk();
        $other = Brand::create(['name' => 'Corsair']);
        $this->patchJson('/api/admin/brands/'.$other->id, ['name' => 'Kingston'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_public_resources_hide_cost_even_for_an_administrator(): void
    {
        $product = Product::create(['category_id' => $this->category->id, 'name' => 'SSD', 'price' => '399.90', 'cost' => '200.00', 'stock' => 1]);
        $this->getJson('/api/products/'.$product->id)->assertOk()->assertJsonMissingPath('data.cost')
            ->assertJsonPath('data.brand', null)->assertJsonPath('data.category.name', 'Componentes');
        $this->getJson('/api/products')->assertOk()->assertJsonMissingPath('data.0.cost');
        $this->getJson('/api/admin/products/'.$product->id)->assertOk()->assertJsonPath('data.cost', '200.00');
        $this->getJson('/api/admin/products')->assertOk()->assertJsonPath('data.0.cost', '200.00');
    }

    public function test_malformed_filters_return_validation_errors_instead_of_server_errors(): void
    {
        foreach ([
            ['per_page' => 0], ['per_page' => 101], ['page' => -1], ['q' => ['invalid']],
            ['brand_ids' => '1'], ['brand_ids' => [1, 1]], ['category_id' => ['invalid']],
            ['min_price' => 10, 'max_price' => 1], ['max_price' => -1],
            ['specifications' => 'invalid'], ['specifications' => ['invalid.key' => [1]]],
            ['specifications' => ['capacity' => 1]], ['specifications' => ['capacity' => [[1]]]],
        ] as $query) {
            $this->getJson('/api/products?'.http_build_query($query))->assertUnprocessable();
        }
        $this->getJson('/api/admin/products?is_active=invalid')->assertUnprocessable()->assertJsonValidationErrors('is_active');
    }

    public function test_json_specification_filters_preserve_types_and_pagination_links(): void
    {
        foreach (['Primeiro', 'Segundo'] as $name) {
            Product::create(['category_id' => $this->category->id, 'name' => $name, 'price' => 100, 'stock' => 1,
                'specifications' => ['capacity' => 16, 'wireless' => false]]);
        }
        Product::create(['category_id' => $this->category->id, 'name' => 'Outra capacidade', 'price' => 100, 'stock' => 1,
            'specifications' => ['capacity' => 32, 'wireless' => true]]);
        $query = http_build_query(['per_page' => 1, 'specifications' => json_encode(['capacity' => [16], 'wireless' => [false]])]);
        $response = $this->getJson('/api/products?'.$query)->assertOk()->assertJsonPath('meta.total', 2);
        $next = $this->getJson($response->json('links.next'))->assertOk()->assertJsonPath('meta.current_page', 2)->assertJsonPath('meta.total', 2);
        $this->assertNotSame($response->json('data.0.id'), $next->json('data.0.id'));
    }

    public function test_only_the_administrative_listing_can_select_inactive_products(): void
    {
        Product::create(['category_id' => $this->category->id, 'name' => 'Ativo', 'price' => 100, 'stock' => 1]);
        Product::create(['category_id' => $this->category->id, 'name' => 'Inativo', 'price' => 100, 'is_active' => false]);
        $this->getJson('/api/products?is_active=0')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'Ativo');
        $this->getJson('/api/admin/products?is_active=0')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'Inativo');
    }
}
