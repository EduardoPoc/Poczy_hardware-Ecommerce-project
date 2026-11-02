<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryStockValidationTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'SenhaAdmin!2026']);
        $admin->forceFill(['role' => 'ADMIN'])->save();
        $this->actingAs($admin, 'web');
        $this->category = Category::create(['name' => 'Componentes']);
        $this->product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Memória',
            'price' => '249.90',
            'stock' => 10,
        ])->refresh();
    }

    public function test_category_can_keep_its_name_and_partial_updates_reject_duplicates(): void
    {
        $this->patchJson('/api/admin/categories/'.$this->category->id, ['name' => 'Componentes'])
            ->assertOk()->assertJsonPath('name', 'Componentes');
        $this->patchJson('/api/admin/categories/'.$this->category->id, ['description' => 'Peças de computador'])
            ->assertOk()->assertJsonPath('name', 'Componentes')->assertJsonPath('description', 'Peças de computador');

        $other = Category::create(['name' => 'Periféricos']);
        $this->patchJson('/api/admin/categories/'.$other->id, ['name' => 'Componentes'])
            ->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_malformed_category_schemas_return_validation_errors_without_persisting(): void
    {
        $this->postJson('/api/admin/categories', [])->assertUnprocessable()->assertJsonValidationErrors('name');
        foreach ([
            ['attributes' => 'invalid'],
            ['attributes' => ['capacity' => 'invalid']],
            ['attributes' => ['capacity' => ['name' => 'Capacidade']]],
            ['attributes' => ['capacity' => ['name' => 'Capacidade', 'type' => 'invalid']]],
            ['attributes' => ['capacity' => ['name' => 'Capacidade', 'type' => 'integer', 'required' => 'true']]],
            ['attributes' => ['capacity' => ['name' => 'Capacidade', 'type' => 'integer', 'minimum' => []]]],
            ['is_active' => ['invalid']],
        ] as $invalid) {
            $this->postJson('/api/admin/categories', array_merge(['name' => 'Categoria inválida'], $invalid))
                ->assertUnprocessable()->assertJsonStructure(['message', 'errors']);
        }
        $this->assertDatabaseCount('categories', 1);
    }

    public function test_category_creation_only_persists_validated_fields_and_keeps_direct_json_format(): void
    {
        $response = $this->postJson('/api/admin/categories', [
            'id' => 999999,
            'name' => 'Monitores',
            'attributes' => ['screen_size' => ['name' => 'Tela', 'type' => 'number', 'minimum' => 1]],
        ])->assertCreated()->assertJsonPath('name', 'Monitores')
            ->assertJsonPath('is_active', true)->assertJsonPath('attributes.screen_size.type', 'number');

        $this->assertNotSame(999999, $response->json('id'));
    }

    public function test_stock_movement_requires_all_fields_and_rejects_malformed_values(): void
    {
        $url = '/api/admin/products/'.$this->product->id.'/stock-movements';
        $this->postJson($url, [])->assertUnprocessable()->assertJsonValidationErrors(['type', 'quantity', 'reason']);
        foreach ([
            ['type' => 'invalid'],
            ['type' => ['INBOUND']],
            ['quantity' => []],
            ['quantity' => 1.5],
            ['quantity' => 0],
            ['quantity' => -1],
            ['quantity' => 2147483648],
            ['reason' => []],
            ['reason' => str_repeat('a', 151)],
        ] as $invalid) {
            $this->postJson($url, array_replace(['type' => 'INBOUND', 'quantity' => 1, 'reason' => 'Reposição'], $invalid))
                ->assertUnprocessable()->assertJsonStructure(['message', 'errors']);
        }
        $this->postJson($url, ['type' => 'ADJUSTMENT', 'quantity' => 0, 'reason' => 'Correção'])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->assertSame(10, $this->product->fresh()->stock);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_valid_signed_adjustment_and_stock_pagination_keep_existing_json_format(): void
    {
        $url = '/api/admin/products/'.$this->product->id.'/stock-movements';
        $this->postJson($url, ['type' => 'ADJUSTMENT', 'quantity' => -2, 'reason' => 'Correção'])
            ->assertCreated()->assertJsonPath('type', 'ADJUSTMENT')->assertJsonPath('quantity', -2);
        $this->assertSame(8, $this->product->fresh()->stock);
        $this->getJson($url.'?per_page=1')->assertOk()->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.quantity', -2);

        foreach (['per_page=0', 'per_page=101', 'per_page=invalid', 'page=0'] as $query) {
            $this->getJson($url.'?'.$query)->assertUnprocessable()->assertJsonStructure(['message', 'errors']);
        }
    }

    public function test_category_and_stock_writes_still_require_an_administrator(): void
    {
        $customer = User::create(['name' => 'Cliente', 'email' => 'customer@example.com', 'password' => 'SenhaCliente!2026']);
        $this->actingAs($customer, 'web');

        $this->postJson('/api/admin/categories', ['name' => 'Monitores'])->assertForbidden();
        $this->patchJson('/api/admin/categories/'.$this->category->id, ['name' => 'Outro nome'])->assertForbidden();
        $this->postJson('/api/admin/products/'.$this->product->id.'/stock-movements', [
            'type' => 'INBOUND', 'quantity' => 1, 'reason' => 'Reposição',
        ])->assertForbidden();
    }
}
