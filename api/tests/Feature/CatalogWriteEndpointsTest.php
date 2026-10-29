<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogWriteEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'SenhaAdmin!2026'])->refresh();
        $this->admin->forceFill(['role' => 'ADMIN'])->save();
        $this->category = Category::create(['name' => 'Componentes', 'attributes' => [
            'capacity_gb' => ['name' => 'Capacidade', 'type' => 'integer', 'required' => true, 'minimum' => 1],
            'wireless' => ['name' => 'Sem fio', 'type' => 'boolean'],
        ]]);
    }

    /** @return array<string, mixed> */
    private function productData(): array
    {
        return ['category_id' => $this->category->id, 'name' => 'Memória', 'price' => '249.90', 'cost' => '170.00',
            'stock' => 10, 'specifications' => ['capacity_gb' => 16, 'wireless' => false]];
    }

    public function test_catalog_writes_and_admin_reads_require_an_administrator(): void
    {
        $this->postJson('/api/product', $this->productData())->assertUnauthorized();
        $customer = User::create(['name' => 'Cliente', 'email' => 'customer@example.com', 'password' => 'SenhaCliente!2026'])->refresh();
        $this->actingAs($customer, 'web');
        $this->postJson('/api/product', $this->productData())->assertForbidden();
        $this->postJson('/api/categoria', ['name' => 'Outra'])->assertForbidden();
        $this->getJson('/api/admin/product')->assertForbidden();
        $this->getJson('/api/brand')->assertForbidden();
    }

    public function test_product_create_update_and_stock_journal_preserve_catalog_contracts(): void
    {
        $this->actingAs($this->admin, 'web');
        $response = $this->postJson('/api/product', $this->productData())->assertCreated()
            ->assertJsonPath('data.is_active', true)->assertJsonPath('data.stock', 10)
            ->assertJsonPath('data.cost', '170.00')->assertJsonPath('data.specifications.wireless', false);
        $id = $response->json('data.id');
        $this->assertDatabaseHas('stock_movements', ['product_id' => $id, 'type' => 'INBOUND', 'quantity' => 10, 'change' => 10]);
        $this->getJson('/api/product/'.$id)->assertOk()->assertJsonMissingPath('data.cost');
        $this->patchJson('/api/product/'.$id, ['price' => '199.90'])->assertOk()->assertJsonPath('data.price', '199.90');
        $this->patchJson('/api/product/'.$id, ['stock' => 100])->assertUnprocessable();
        $this->deleteJson('/api/product/'.$id)->assertConflict();
        $this->patchJson('/api/product/'.$id, ['is_active' => false])->assertOk();
        $this->getJson('/api/product/'.$id)->assertNotFound();
        $this->getJson('/api/admin/product/'.$id)->assertOk()->assertJsonPath('data.is_active', false);
        $this->getJson('/api/admin/product')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/product/'.$id.'/stock-movements')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_product_deletion_without_history_and_category_conflicts(): void
    {
        $this->actingAs($this->admin, 'web');
        $data = $this->productData();
        $data['stock'] = 0;
        $id = $this->postJson('/api/product', $data)->assertCreated()->json('data.id');
        $this->deleteJson('/api/categoria/'.$this->category->id)->assertConflict();
        $this->deleteJson('/api/product/'.$id)->assertNoContent();
        $this->deleteJson('/api/categoria/'.$this->category->id)->assertNoContent();
    }

    public function test_product_specifications_enforce_required_types_options_and_limits_on_create_and_update(): void
    {
        $this->actingAs($this->admin, 'web');
        foreach ([[], ['capacity_gb' => '16'], ['capacity_gb' => 0], ['capacity_gb' => 16, 'wireless' => 0], ['capacity_gb' => 16, 'unknown' => true], ['capacity_gb' => 16, 'brand' => 'Poczy']] as $values) {
            $data = $this->productData();
            $data['specifications'] = $values;
            $this->postJson('/api/product', $data)->assertUnprocessable();
        }
        $data = $this->productData();
        $data['price'] = '0.001';
        $this->postJson('/api/product', $data)->assertUnprocessable();
        $this->assertDatabaseCount('products', 0);
        $id = $this->postJson('/api/product', $this->productData())->assertCreated()->json('data.id');
        $this->patchJson('/api/product/'.$id, ['specifications' => null])->assertUnprocessable();
        $other = Category::create(['name' => 'Outra', 'attributes' => ['socket' => ['name' => 'Socket', 'type' => 'text', 'required' => true, 'options' => ['AM5']]]]);
        $this->patchJson('/api/product/'.$id, ['category_id' => $other->id])->assertUnprocessable();
        $this->patchJson('/api/product/'.$id, ['category_id' => $other->id, 'specifications' => ['socket' => 'AM5']])->assertOk();
    }

    public function test_categories_validate_definitions_and_cannot_invalidate_existing_products(): void
    {
        $this->actingAs($this->admin, 'web');
        $this->postJson('/api/categoria', ['name' => 'Nova', 'attributes' => ['bad->key' => ['name' => 'Inválida', 'type' => 'text']]])->assertUnprocessable();
        $this->postJson('/api/categoria', ['name' => 'Nova', 'attributes' => ['size' => ['name' => 'Inválida', 'type' => 'integer', 'options' => ['16']]]])->assertUnprocessable();
        $this->postJson('/api/categoria', ['name' => 'Nova', 'attributes' => ['size' => ['name' => 'Inválida', 'type' => 'integer', 'minimum' => 10, 'maximum' => 1]]])->assertUnprocessable();
        $this->postJson('/api/categoria', ['name' => 'Nova', 'attributes' => ['size' => ['name' => 'Inválida', 'type' => 'text', 'minimum' => 1]]])->assertUnprocessable();
        $this->postJson('/api/categoria', ['name' => 'Nova', 'is_active' => false])->assertCreated()->assertJsonPath('data.is_active', false);
        $this->getJson('/api/admin/categoria')->assertOk()->assertJsonCount(2, 'data');
        $this->postJson('/api/product', $this->productData())->assertCreated();
        $this->patchJson('/api/categoria/'.$this->category->id, ['attributes' => null])->assertUnprocessable();
        $this->patchJson('/api/categoria/'.$this->category->id, ['description' => 'Nova descrição'])->assertOk();
        $this->getJson('/api/categoria/'.$this->category->id)->assertOk()->assertJsonPath('data.attributes.capacity_gb.type', 'integer');
        $this->patchJson('/api/categoria/'.$this->category->id, ['name' => 'Componentes'])->assertOk();
    }

    public function test_stock_entries_exits_and_adjustments_are_atomic_and_cannot_be_negative(): void
    {
        $this->actingAs($this->admin, 'web');
        $id = $this->postJson('/api/product', $this->productData())->assertCreated()->json('data.id');
        $uri = '/api/product/'.$id.'/stock-movements';
        $this->postJson($uri, ['type' => 'OUTBOUND', 'change' => -3, 'reason' => 'Venda física'])->assertCreated()->assertJsonPath('data.quantity', 3);
        $this->assertDatabaseHas('products', ['id' => $id, 'stock' => 7]);
        $this->postJson($uri, ['type' => 'OUTBOUND', 'change' => -8, 'reason' => 'Saldo insuficiente'])->assertUnprocessable();
        $this->postJson($uri, ['type' => 'INBOUND', 'change' => -1, 'reason' => 'Sinal inválido'])->assertUnprocessable();
        $this->postJson($uri, ['type' => 'ADJUSTMENT', 'change' => 0, 'reason' => 'Sem efeito'])->assertUnprocessable();
        $this->postJson($uri, ['type' => 'ADJUSTMENT', 'change' => 2, 'reason' => 'Conferência'])->assertCreated();
        $this->assertDatabaseHas('products', ['id' => $id, 'stock' => 9]);
        $this->assertDatabaseCount('stock_movements', 3);
    }

    public function test_brand_crud_validates_unique_names_and_preserves_products_on_deletion(): void
    {
        $this->actingAs($this->admin, 'web');
        $id = $this->postJson('/api/brand', ['name' => 'Marca'])->assertCreated()->json('data.id');
        $this->postJson('/api/brand', ['name' => 'Marca'])->assertUnprocessable();
        $this->getJson('/api/brand/'.$id)->assertOk();
        $this->patchJson('/api/brand/'.$id, ['name' => 'Marca'])->assertOk();
        $data = $this->productData();
        $data['brand_id'] = $id;
        $productId = $this->postJson('/api/product', $data)->assertCreated()->json('data.id');
        $this->deleteJson('/api/brand/'.$id)->assertNoContent();
        $this->assertDatabaseHas('products', ['id' => $productId, 'brand_id' => null]);
    }

    public function test_malformed_and_oversized_identifiers_return_404_instead_of_server_errors(): void
    {
        $this->actingAs($this->admin, 'web');
        foreach (['invalid', '999999999999999999999999999999', '-1', '0'] as $id) {
            $this->getJson('/api/categoria/'.$id)->assertNotFound();
            $this->deleteJson('/api/product/'.$id)->assertNotFound();
        }
    }
}
