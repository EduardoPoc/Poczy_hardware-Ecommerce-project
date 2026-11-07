<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\CategoryService;
use App\Services\ProductService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicGetEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_products_are_paginated_with_stable_order_and_decimal_prices(): void
    {
        $response = $this->getJson('/api/products')->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('meta.total', 48);
        foreach ($response->json('data') as $product) {
            $this->assertIsString($product['price']);
        }
        $first = $this->getJson('/api/products?per_page=2')->assertOk();
        $second = $this->getJson('/api/products?per_page=2&page=2')->assertOk()->assertJsonPath('meta.current_page', 2);
        $this->assertEmpty(array_intersect(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id')));
    }

    public function test_product_detail_loads_category_brand_and_json_specifications(): void
    {
        $product = Product::where('name', 'Monitor LG 23,8 IPS Full HD')->sole();
        $this->getJson('/api/products/'.$product->id)->assertOk()
            ->assertJsonPath('data.name', $product->name)->assertJsonPath('data.category.name', 'Monitores')
            ->assertJsonPath('data.brand.name', 'LG')->assertJsonPath('data.specifications.screen_size_inches', 23.8);
    }

    public function test_public_queries_hide_inactive_products_and_categories(): void
    {
        $product = Product::where('name', 'Monitor LG 23,8 IPS Full HD')->sole();
        $product->update(['is_active' => false]);
        Category::where('name', 'Notebooks')->sole()->update(['is_active' => false]);
        $this->getJson('/api/products/'.$product->id)->assertNotFound();
        $this->getJson('/api/products')->assertOk()->assertJsonPath('meta.total', 43);
        $this->getJson('/api/products/invalid')->assertNotFound();
        $this->getJson('/api/products/999999')->assertNotFound();
    }

    public function test_brands_are_public_and_return_all_seeded_brands(): void
    {
        $response = $this->getJson('/api/brands')->assertOk()->assertJsonCount(30, 'data');
        $brand = $response->json('data.0');
        $this->getJson('/api/brands/'.$brand['id'])->assertOk()->assertJsonPath('data.name', $brand['name']);
    }

    public function test_catalog_uses_public_service_filters_and_does_not_expose_internal_costs(): void
    {
        $product = Product::where('name', 'Monitor LG 23,8 IPS Full HD')->sole();
        $product->update(['is_active' => false]);
        $category = Category::where('name', 'Notebooks')->sole();
        $category->update(['is_active' => false]);
        $response = $this->getJson('/api/catalog')->assertOk()
            ->assertJsonCount(11, 'data.categories')->assertJsonCount(43, 'data.products');
        $this->assertNotContains($category->id, array_column($response->json('data.categories'), 'id'));
        $this->assertNotContains($product->id, array_column($response->json('data.products'), 'id'));
        foreach ($response->json('data.products') as $record) {
            $this->assertArrayNotHasKey('cost', $record);
            $this->assertIsString($record['price']);
        }
    }

    public function test_http_search_combines_name_brand_and_inclusive_price_limits_without_requiring_a_category(): void
    {
        $product = Product::where('name', 'Notebook Acer Gamer 15,6 IPS 16 GB SSD 1 TB')->sole();
        $query = http_build_query(['q' => 'Acer', 'brand_ids' => [$product->brand_id], 'min_price' => $product->price, 'max_price' => $product->price]);
        $this->getJson('/api/products?'.$query)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $product->id);
    }

    public function test_mysql_json_filters_combine_alternatives_and_preserve_boolean_and_numeric_types(): void
    {
        $category = Category::where('name', 'PCs montados')->sole();
        $service = app(ProductService::class);
        $filtered = $service->filterPublicProducts(['category_id' => $category->id,
            'specifications' => ['ram_capacity_gb' => [16, 32], 'has_dedicated_gpu' => [false]]]);
        $this->assertSame(['PC Poczy Essencial 16 GB SSD 512 GB'], $filtered->pluck('name')->all());
        $monitors = Category::where('name', 'Monitores')->sole();
        $this->assertSame(2, $service->filterPublicProducts(['category_id' => $monitors->id,
            'specifications' => ['screen_size_inches' => [23.8]]])->total());
        $this->assertSame(4, $service->filterPublicProducts(['category_id' => $monitors->id,
            'specifications' => ['screen_size_inches' => []]])->total());
    }

    public function test_available_filters_reflect_active_products_and_keep_false_values(): void
    {
        $category = Category::where('name', 'PCs montados')->sole();
        $filters = app(CategoryService::class)->availableFilters($category->id);
        $this->assertSame([false, true], $filters['attributes']['has_dedicated_gpu']['values']);
        $this->assertSame('Poczy', $filters['brands'][0]['name']);
        $this->assertSame('1899.90', $filters['min_price']);
        $this->assertSame('8499.90', $filters['max_price']);
    }

    public function test_seeding_twice_preserves_data_and_does_not_duplicate_the_catalog_or_stock_journal(): void
    {
        $product = Product::where('stock', '>', 0)->firstOrFail();
        $product->update(['stock' => 1]);
        $this->seed(DatabaseSeeder::class);
        $this->assertDatabaseCount('products', 49);
        $this->assertDatabaseCount('categories', 12);
        $this->assertDatabaseCount('brands', 30);
        $this->assertDatabaseCount('users', 7);
        $this->assertDatabaseCount('addresses', 7);
        $this->assertDatabaseCount('stock_movements', 53);
        $this->assertSame(1, $product->fresh()->stock);
    }
}
