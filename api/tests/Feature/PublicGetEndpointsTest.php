<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PublicGetEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProductSeeder::class);
    }

    public function test_product_cards_are_paginated_and_only_expose_public_card_fields(): void
    {
        $response = $this->getJson('/api/product')->assertOk()
            ->assertJsonCount(20, 'data')->assertJsonPath('meta.total', 24);
        foreach ($response->json('data') as $card) {
            $this->assertEqualsCanonicalizing(['id', 'name', 'price', 'image_url'], array_keys($card));
            $this->assertIsString($card['price']);
        }
        $first = $this->getJson('/api/product?per_page=2&page=1')->assertOk();
        $second = $this->getJson('/api/product?per_page=2&page=2')->assertOk()->assertJsonPath('meta.current_page', 2);
        $this->assertEmpty(array_intersect(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id')));
    }

    public function test_product_detail_includes_specifications_and_relationships_without_internal_costs(): void
    {
        $product = Product::where('name', 'Monitor LG 23,8 IPS Full HD')->sole();
        $this->getJson('/api/product/'.$product->id)->assertOk()
            ->assertJsonPath('data.name', $product->name)
            ->assertJsonPath('data.category.name', 'Monitores')
            ->assertJsonPath('data.brand.name', 'LG')
            ->assertJsonPath('data.specifications.screen_size_inches', 23.8)
            ->assertJsonPath('data.image_url', null)
            ->assertJsonMissingPath('data.cost')
            ->assertJsonMissingPath('data.stock_movements')
            ->assertJsonMissingPath('data.order_items');
    }

    public function test_inactive_products_and_categories_are_hidden_in_lists_and_details(): void
    {
        $product = Product::where('name', 'Monitor LG 23,8 IPS Full HD')->sole();
        $product->update(['is_active' => false]);
        $this->getJson('/api/product/'.$product->id)->assertNotFound();
        Category::where('name', 'Notebooks')->sole()->update(['is_active' => false]);
        $notebook = Product::where('name', 'Notebook Lenovo 15,6 IPS 16 GB SSD 512 GB')->sole();
        $this->getJson('/api/product/'.$notebook->id)->assertNotFound();
        $this->getJson('/api/product')->assertOk()->assertJsonPath('meta.total', 21);
        $this->getJson('/api/categoria')->assertOk()->assertJsonCount(11, 'data');
        $this->getJson('/api/product/999999')->assertNotFound();
        $this->getJson('/api/product/invalid')->assertNotFound();
    }

    public function test_category_list_only_includes_ids_and_names(): void
    {
        $response = $this->getJson('/api/categoria')->assertOk()->assertJsonCount(12, 'data');
        foreach ($response->json('data') as $category) {
            $this->assertEqualsCanonicalizing(['id', 'name'], array_keys($category));
        }
    }

    public function test_filter_combines_alternatives_and_preserves_boolean_false(): void
    {
        $category = Category::where('name', 'PCs montados')->sole();
        $this->filter([
            'category_id' => $category->id,
            'specifications' => ['ram_capacity_gb' => [16, 32], 'has_dedicated_gpu' => [false]],
        ])->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'PC Poczy Essencial 16 GB SSD 512 GB');
        $this->filter([
            'category_id' => $category->id,
            'specifications' => ['ram_capacity_gb' => [16, 32], 'has_dedicated_gpu' => [false, true]],
        ])->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_price_limits_are_inclusive_and_combine_with_brands_and_specifications(): void
    {
        $product = Product::where('name', 'Notebook Acer Gamer 15,6 IPS 16 GB SSD 1 TB')->sole();
        $this->filter([
            'category_id' => $product->category_id,
            'min_price' => $product->price, 'max_price' => $product->price,
            'brand_ids' => [$product->brand_id], 'specifications' => ['has_dedicated_gpu' => [true]],
        ])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $product->id);
    }

    public function test_empty_alternative_lists_do_not_restrict_results_and_no_matches_return_an_empty_collection(): void
    {
        $category = Category::where('name', 'Memórias RAM')->sole();
        $this->filter(['category_id' => $category->id, 'brand_ids' => [], 'specifications' => ['capacity_gb' => []]])
            ->assertOk()->assertJsonCount(2, 'data');
        $this->filter(['category_id' => $category->id, 'specifications' => ['capacity_gb' => [64]]])
            ->assertOk()->assertJsonPath('data', [])->assertJsonPath('meta.total', 0);
    }

    public function test_filter_rejects_unknown_or_disabled_attributes_and_values_of_the_wrong_type(): void
    {
        $category = Category::where('name', 'PCs montados')->sole();
        foreach ([['unknown' => [16]], ['ram_capacity_gb' => ['16']], ['has_dedicated_gpu' => [0]], ['ram_capacity_gb' => [0]], ["ram') or 1=1--" => [16]]] as $specifications) {
            $this->filter(['category_id' => $category->id, 'specifications' => $specifications])->assertUnprocessable();
        }
        $definitions = $category->getAttribute('attributes');
        $definitions['ram_capacity_gb']['is_filterable'] = false;
        $category->update(['attributes' => $definitions]);
        $this->filter(['category_id' => $category->id, 'specifications' => ['ram_capacity_gb' => [16]]])->assertUnprocessable();
    }

    public function test_filter_validates_configured_options_and_active_category(): void
    {
        $category = Category::where('name', 'Monitores')->sole();
        $this->filter(['category_id' => $category->id, 'specifications' => ['screen_type' => ['unknown']]])->assertUnprocessable();
        $this->filter(['category_id' => $category->id, 'brand_ids' => [999999]])->assertUnprocessable();
        $this->filter(['category_id' => $category->id, 'min_price' => 1000, 'max_price' => 100])->assertUnprocessable();
        $category->update(['is_active' => false]);
        $this->filter(['category_id' => $category->id])->assertUnprocessable();
    }

    public function test_malformed_filters_and_invalid_pagination_return_json_errors(): void
    {
        foreach (['{', 'null', '[]', 'true'] as $filter) {
            $this->getJson('/api/categoria/filter?'.http_build_query(['filter' => $filter]))->assertUnprocessable();
        }
        $this->getJson('/api/categoria/filter')->assertUnprocessable();
        foreach (['per_page=0', 'per_page=101', 'page=0', 'page=text'] as $query) {
            $this->getJson('/api/product?'.$query)->assertUnprocessable();
        }
    }

    public function test_number_options_accept_equivalent_integer_and_decimal_values(): void
    {
        $category = Category::where('name', 'Monitores')->sole();
        $this->filter(['category_id' => $category->id, 'specifications' => ['screen_size_inches' => [23.8]]])->assertOk()->assertJsonCount(1, 'data');
        $definitions = $category->getAttribute('attributes');
        $definitions['screen_size_inches']['options'] = [27];
        $category->update(['attributes' => $definitions]);
        $this->getJson('/api/categoria/filter?filter='.rawurlencode('{"category_id":'.$category->id.',"specifications":{"screen_size_inches":[27.0]}}'))
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_filters_reject_non_finite_numbers_and_get_request_bodies(): void
    {
        $category = Category::where('name', 'Monitores')->sole();
        $this->getJson('/api/categoria/filter?filter='.rawurlencode('{"category_id":'.$category->id.',"specifications":{"screen_size_inches":[1e999]}}'))
            ->assertUnprocessable();
        $this->json('GET', '/api/categoria/filter', ['filter' => ['category_id' => $category->id]])->assertUnprocessable();
    }

    public function test_old_versioned_urls_are_not_registered(): void
    {
        foreach (['/api/v1/health', '/api/v1/catalog', '/api/v1/user'] as $path) {
            $this->getJson($path)->assertNotFound();
        }
    }

    /** @param array<string, mixed> $filter */
    private function filter(array $filter): TestResponse
    {
        return $this->getJson('/api/categoria/filter?'.http_build_query(['filter' => json_encode($filter, JSON_THROW_ON_ERROR)]));
    }
}
