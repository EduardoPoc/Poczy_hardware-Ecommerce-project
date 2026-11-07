<?php

namespace Tests\Feature;

use App\Http\Requests\StoreProductRequest;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CatalogInputValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $admin = new User(['name' => 'Admin', 'email' => 'admin@example.com']);
        $admin->id = 1;
        $admin->role = 'ADMIN';
        $this->actingAs($admin, 'web');
    }

    public function test_category_creation_validates_required_name_and_malformed_schemas_before_persistence(): void
    {
        $this->postJson('/api/admin/categories', [])->assertUnprocessable()
            ->assertJsonValidationErrors('name')->assertJsonStructure(['message', 'errors']);

        foreach ([
            ['attributes' => 'invalid'],
            ['attributes' => ['capacity' => 'invalid']],
            ['attributes' => ['capacity' => ['name' => 'Capacidade', 'type' => 'invalid']]],
            ['attributes' => ['capacity' => ['name' => 'Capacidade', 'type' => 'integer', 'required' => 'true']]],
            ['attributes' => ['capacity' => ['name' => 'Capacidade', 'type' => 'integer', 'minimum' => []]]],
        ] as $payload) {
            $response = $this->postJson('/api/admin/categories', $payload)->assertUnprocessable()
                ->assertJsonValidationErrors('name')->assertJsonStructure(['message', 'errors']);

            $this->assertGreaterThan(1, count($response->json('errors')));
        }
    }

    public function test_stock_creation_rejects_missing_and_malformed_fields_before_loading_product(): void
    {
        $url = '/api/admin/products/1/stock-movements';
        $this->postJson($url, [])->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'quantity', 'reason'])
            ->assertJsonStructure(['message', 'errors']);

        foreach ([
            ['type' => 'invalid'],
            ['type' => ['INBOUND']],
            ['quantity' => []],
            ['quantity' => 0],
            ['quantity' => 1.5],
            ['quantity' => 2147483648],
            ['reason' => null],
        ] as $invalid) {
            $payload = array_replace(['type' => 'INBOUND', 'quantity' => 1, 'reason' => 'Reposição'], $invalid);
            $this->postJson($url, $payload)->assertUnprocessable()
                ->assertJsonValidationErrors(array_keys($invalid))
                ->assertJsonStructure(['message', 'errors']);
        }

        $this->postJson($url, ['type' => 'OUTBOUND', 'quantity' => -1, 'reason' => 'Saída'])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');
    }

    public function test_stock_pagination_rejects_invalid_values_before_loading_product(): void
    {
        foreach ([
            'per_page=0', 'per_page=101', 'per_page=invalid', 'page=0', 'page=invalid',
        ] as $query) {
            $this->getJson('/api/admin/products/1/stock-movements?'.$query)->assertUnprocessable()
                ->assertJsonStructure(['message', 'errors']);
        }
    }

    public function test_initial_stock_stays_within_the_same_balance_limits_as_movements(): void
    {
        $rules = ['stock' => (new StoreProductRequest)->rules()['stock']];
        foreach ([0, 2147483647] as $stock) {
            $this->assertTrue(Validator::make(['stock' => $stock], $rules)->passes());
        }
        foreach ([-1, 2147483648] as $stock) {
            $this->assertTrue(Validator::make(['stock' => $stock], $rules)->fails());
        }
    }

    public function test_public_filter_rejects_malformed_json_and_scalar_values_before_querying_products(): void
    {
        foreach (['invalid-json', '{', 'null', 'true', '10'] as $filter) {
            $this->getJson('/api/products/filter?'.http_build_query(['filter' => $filter]))
                ->assertUnprocessable()->assertJsonValidationErrors('filter')
                ->assertJsonStructure(['message', 'errors']);
        }
    }

    public function test_public_filter_rejects_malformed_nested_values_and_pagination_before_querying_products(): void
    {
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

    public function test_unknown_sort_modes_are_rejected_before_querying_products(): void
    {
        $this->getJson('/api/products/filter?'.http_build_query(['filter' => ['sort' => 'unsafe_sql']]))
            ->assertUnprocessable()->assertJsonValidationErrors('filter.sort');
    }
}
