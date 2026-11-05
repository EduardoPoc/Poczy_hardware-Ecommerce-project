<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Services\CategoryService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
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

    public function test_catalog_writes_require_an_administrator(): void
    {
        $this->postJson('/api/admin/products', $this->productData())->assertUnauthorized();
        $this->actingAs(User::create(['name' => 'Cliente', 'email' => 'customer@example.com', 'password' => 'SenhaCliente!2026']), 'web');
        $this->postJson('/api/admin/products', $this->productData())->assertForbidden();
        $this->postJson('/api/admin/brands', ['name' => 'Marca'])->assertForbidden();
    }

    public function test_product_writes_preserve_stock_history_and_public_visibility(): void
    {
        $this->actingAs($this->admin, 'web');
        $id = $this->postJson('/api/admin/products', $this->productData())->assertCreated()
            ->assertJsonPath('data.is_active', true)->assertJsonPath('data.stock', 10)->assertJsonPath('data.cost', '170.00')->json('data.id');
        $this->assertDatabaseHas('stock_movements', ['product_id' => $id, 'type' => 'INBOUND', 'quantity' => 10]);
        $this->patchJson('/api/admin/products/'.$id, ['price' => '199.90'])->assertOk()->assertJsonPath('data.price', '199.90');
        $this->patchJson('/api/admin/products/'.$id, ['stock' => 100])->assertUnprocessable()->assertJsonValidationErrors('stock');
        $this->deleteJson('/api/admin/products/'.$id)->assertConflict();
        $this->patchJson('/api/admin/products/'.$id, ['is_active' => false])->assertOk();
        $this->getJson('/api/products/'.$id)->assertNotFound();
        $this->getJson('/api/admin/products/'.$id)->assertOk()->assertJsonPath('data.is_active', false);
        $this->getJson('/api/admin/products')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_products_without_history_can_be_deleted_and_linked_categories_are_preserved(): void
    {
        $this->actingAs($this->admin, 'web');
        $data = $this->productData();
        $data['stock'] = 0;
        $data['is_active'] = false;
        $id = $this->postJson('/api/admin/products', $data)->assertCreated()->json('data.id');
        try {
            app(CategoryService::class)->delete($this->category->id);
            $this->fail('A categoria com produto deve ser preservada.');
        } catch (ConflictHttpException) {
            $this->assertDatabaseHas('categories', ['id' => $this->category->id]);
        }
        $this->deleteJson('/api/admin/products/'.$id)->assertNoContent();
        app(CategoryService::class)->delete($this->category->id);
        $this->assertDatabaseMissing('categories', ['id' => $this->category->id]);
    }

    public function test_specification_business_rules_are_applied_to_product_writes(): void
    {
        $this->actingAs($this->admin, 'web');
        foreach ([[], ['capacity_gb' => '16'], ['capacity_gb' => 0], ['capacity_gb' => 16, 'wireless' => 0], ['capacity_gb' => 16, 'unknown' => true]] as $values) {
            $data = $this->productData();
            $data['specifications'] = $values;
            $this->postJson('/api/admin/products', $data)->assertUnprocessable();
        }
        $this->assertDatabaseCount('products', 0);
        $id = $this->postJson('/api/admin/products', $this->productData())->assertCreated()->json('data.id');
        $this->patchJson('/api/admin/products/'.$id, ['specifications' => null])->assertUnprocessable();
        $other = Category::create(['name' => 'Outra', 'attributes' => ['socket' => ['name' => 'Socket', 'type' => 'text', 'required' => true, 'options' => ['AM5']]]]);
        $this->patchJson('/api/admin/products/'.$id, ['category_id' => $other->id])->assertUnprocessable();
        $this->patchJson('/api/admin/products/'.$id, ['category_id' => $other->id, 'specifications' => ['socket' => 'AM5']])->assertOk();
    }

    public function test_stock_entries_exits_and_signed_adjustments_are_atomic(): void
    {
        $this->actingAs($this->admin, 'web');
        $id = $this->postJson('/api/admin/products', $this->productData())->assertCreated()->json('data.id');
        $stock = app(StockService::class);
        $this->assertSame(3, $stock->create($id, ['type' => 'OUTBOUND', 'quantity' => 3])->quantity);
        try {
            $stock->create($id, ['type' => 'OUTBOUND', 'quantity' => 8]);
            $this->fail('Saída maior que o saldo deve falhar.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('products', ['id' => $id, 'stock' => 7]);
        }
        $stock->create($id, ['type' => 'ADJUSTMENT', 'quantity' => 2]);
        $this->assertSame(-2, $stock->create($id, ['type' => 'ADJUSTMENT', 'quantity' => -2])->quantity);
        $this->assertDatabaseHas('products', ['id' => $id, 'stock' => 7]);
        $this->assertFalse(Schema::hasColumn('stock_movements', 'change'));
        $this->assertDatabaseCount('stock_movements', 4);
    }

    public function test_brand_crud_and_mysql_foreign_key_preserve_products(): void
    {
        $this->actingAs($this->admin, 'web');
        $id = $this->postJson('/api/admin/brands', ['name' => 'Marca'])->assertCreated()->json('data.id');
        $this->postJson('/api/admin/brands', ['name' => 'Marca'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->getJson('/api/brands/'.$id)->assertOk();
        $this->patchJson('/api/admin/brands/'.$id, ['name' => 'Marca atualizada'])->assertOk();
        $productId = $this->postJson('/api/admin/products', $this->productData() + ['brand_id' => $id])->assertCreated()->json('data.id');
        $this->deleteJson('/api/admin/brands/'.$id)->assertNoContent();
        $this->assertDatabaseHas('products', ['id' => $productId, 'brand_id' => null]);
    }
}
