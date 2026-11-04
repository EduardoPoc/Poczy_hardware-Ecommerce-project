<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\CatalogService;
use App\Services\CategoryService;
use App\Services\StockService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\PresenceVerifierInterface;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CatalogLayerIntegrationTest extends TestCase
{
    private function category(bool $active = true): Category
    {
        $category = new Category([
            'name' => 'Componentes',
            'attributes' => ['capacity' => ['name' => 'Capacidade', 'type' => 'integer']],
            'is_active' => $active,
        ]);
        $category->id = 31;
        $category->wasRecentlyCreated = true;

        return $category;
    }

    private function actAsAdmin(): void
    {
        $user = new User(['name' => 'Admin', 'email' => 'admin@example.com']);
        $user->id = 1;
        $user->role = 'ADMIN';
        $this->actingAs($user, 'web');
    }

    public function test_catalog_delegates_to_its_service_and_hides_product_costs(): void
    {
        $category = $this->category();
        $product = new Product([
            'category_id' => $category->id,
            'name' => 'Memória',
            'price' => '249.90',
            'cost' => '170.00',
            'is_active' => true,
            'stock' => 10,
        ]);
        $product->id = 41;
        $this->mock(CatalogService::class, function (MockInterface $mock) use ($category, $product): void {
            $mock->shouldReceive('getCatalog')->once()->withNoArgs()->andReturn([
                'categories' => new Collection([$category]),
                'products' => new Collection([$product]),
            ]);
        });

        $this->getJson('/api/catalog')->assertOk()
            ->assertJsonPath('data.categories.0.id', 31)
            ->assertJsonPath('data.categories.0.attributes.capacity.type', 'integer')
            ->assertJsonPath('data.products.0.id', 41)
            ->assertJsonPath('data.products.0.price', '249.90')
            ->assertJsonMissingPath('data.products.0.cost');
    }

    public function test_public_categories_delegate_to_public_service_methods_and_return_resources(): void
    {
        $category = $this->category();
        $this->mock(CategoryService::class, function (MockInterface $mock) use ($category): void {
            $mock->shouldReceive('listPublicCategories')->once()->withNoArgs()->andReturn(new Collection([$category]));
            $mock->shouldReceive('getPublicCategory')->once()->with(31)->andReturn($category);
        });

        $this->getJson('/api/categories')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 31)->assertJsonPath('data.0.is_active', true);
        $this->getJson('/api/categories/31')->assertOk()
            ->assertJsonPath('data.id', 31)->assertJsonPath('data.attributes.capacity.type', 'integer');
    }

    public function test_administrative_categories_delegate_to_unrestricted_service_methods_and_include_inactive_records(): void
    {
        $this->actAsAdmin();
        $category = $this->category(false);
        $this->mock(CategoryService::class, function (MockInterface $mock) use ($category): void {
            $mock->shouldReceive('listAll')->once()->withNoArgs()->andReturn(new Collection([$category]));
            $mock->shouldReceive('findById')->once()->with(31)->andReturn($category);
        });

        $this->getJson('/api/admin/categories')->assertOk()
            ->assertJsonPath('data.0.id', 31)->assertJsonPath('data.0.is_active', false);
        $this->getJson('/api/admin/categories/31')->assertOk()
            ->assertJsonPath('data.id', 31)->assertJsonPath('data.is_active', false);
    }

    public function test_public_filter_options_preserve_boolean_and_numeric_values(): void
    {
        $filters = [
            'brands' => [['id' => 7, 'name' => 'Marca']],
            'attributes' => [
                'dedicated_gpu' => ['name' => 'GPU dedicada', 'type' => 'boolean', 'values' => [false, true]],
                'capacity' => ['name' => 'Capacidade', 'type' => 'integer', 'values' => [8, 16]],
            ],
            'min_price' => '249.90',
            'max_price' => '499.90',
        ];
        $this->mock(CategoryService::class, function (MockInterface $mock) use ($filters): void {
            $mock->shouldReceive('availableFilters')->once()->with(31)->andReturn($filters);
        });

        $this->getJson('/api/categories/31/filters')->assertOk()
            ->assertExactJson(['data' => $filters]);
        $this->getJson('/api/categories/not-a-number/filters')->assertNotFound();
    }

    public function test_category_creation_forwards_validated_data_and_explicitly_returns_created(): void
    {
        $this->actAsAdmin();
        $category = $this->category();
        $category->wasRecentlyCreated = false;
        $verifier = Mockery::mock(PresenceVerifierInterface::class);
        $verifier->shouldReceive('getCount')->once()->andReturn(0);
        $this->app['validator']->setPresenceVerifier($verifier);
        $this->mock(CategoryService::class, function (MockInterface $mock) use ($category): void {
            $mock->shouldReceive('create')->once()->with(['name' => 'Componentes'])->andReturn($category);
        });

        $this->postJson('/api/admin/categories', ['name' => 'Componentes', 'id' => 999999])
            ->assertCreated()->assertJsonPath('data.id', 31)->assertJsonPath('data.name', 'Componentes');
    }

    public function test_category_update_forwards_only_validated_changes_and_explicitly_returns_ok(): void
    {
        $this->actAsAdmin();
        $category = $this->category();
        $category->description = 'Descrição atualizada';
        $this->mock(CategoryService::class, function (MockInterface $mock) use ($category): void {
            $mock->shouldReceive('update')->once()->with(31, ['description' => 'Descrição atualizada'])->andReturn($category);
        });

        $this->patchJson('/api/admin/categories/31', ['description' => 'Descrição atualizada', 'id' => 999999])
            ->assertOk()->assertJsonPath('data.id', 31)->assertJsonPath('data.description', 'Descrição atualizada');
    }

    public function test_category_deletion_delegates_to_service_and_keeps_the_empty_response(): void
    {
        $this->actAsAdmin();
        $this->mock(CategoryService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('delete')->once()->with(31)->andReturnNull();
        });

        $this->deleteJson('/api/admin/categories/31')->assertNoContent();
    }

    public function test_stock_listing_forwards_validated_pagination_and_returns_resource_metadata(): void
    {
        $this->actAsAdmin();
        $movement = new StockMovement(['type' => 'INBOUND', 'quantity' => 2, 'reason' => 'Reposição']);
        $movement->id = 51;
        $movement->product_id = 41;
        $paginator = new LengthAwarePaginator(new Collection([$movement]), 2, 1, 1, [
            'path' => url('/api/admin/products/41/stock-movements'),
        ]);
        $this->mock(StockService::class, function (MockInterface $mock) use ($paginator): void {
            $mock->shouldReceive('listMovements')->once()->with(41, 1)->andReturn($paginator);
        });

        $this->getJson('/api/admin/products/41/stock-movements?per_page=1')->assertOk()
            ->assertJsonPath('data.0.id', 51)->assertJsonPath('data.0.product_id', 41)
            ->assertJsonPath('data.0.quantity', 2)->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 1)->assertJsonStructure(['links' => ['next'], 'meta']);
    }

    public function test_stock_creation_forwards_only_validated_fields_and_explicitly_returns_created(): void
    {
        $this->actAsAdmin();
        $data = ['type' => 'INBOUND', 'quantity' => 2, 'reason' => 'Reposição'];
        $movement = new StockMovement($data);
        $movement->id = 51;
        $movement->product_id = 41;
        $movement->wasRecentlyCreated = false;
        $this->mock(StockService::class, function (MockInterface $mock) use ($movement, $data): void {
            $mock->shouldReceive('create')->once()->with(41, $data)->andReturn($movement);
        });

        $this->postJson('/api/admin/products/41/stock-movements', $data + ['id' => 999999])
            ->assertCreated()->assertJsonPath('data.id', 51)->assertJsonPath('data.product_id', 41)
            ->assertJsonPath('data.type', 'INBOUND')->assertJsonPath('data.quantity', 2);
    }
}
