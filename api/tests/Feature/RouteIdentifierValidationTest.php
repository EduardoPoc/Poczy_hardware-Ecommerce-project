<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use App\Services\CategoryService;
use Tests\TestCase;

class RouteIdentifierValidationTest extends TestCase
{
    public function test_unrepresentable_numeric_identifiers_return_404_before_services_run(): void
    {
        $admin = new User(['name' => 'Admin']);
        $admin->id = 1;
        $admin->role = 'ADMIN';
        $this->actingAs($admin, 'web');
        foreach ([str_repeat('9', 30), str_repeat('9', strlen((string) PHP_INT_MAX))] as $identifier) {
            foreach ([
                '/api/products/', '/api/brands/', '/api/categories/', '/api/addresses/', '/api/orders/',
                '/api/admin/products/', '/api/admin/brands/', '/api/admin/categories/', '/api/admin/orders/',
            ] as $prefix) {
                $this->getJson($prefix.$identifier)->assertNotFound()->assertJsonStructure(['message']);
            }
            $this->postJson('/api/orders/'.$identifier.'/pay')->assertNotFound();
            $this->getJson('/api/admin/products/'.$identifier.'/stock-movements')->assertNotFound();
            $this->patchJson('/api/cart/items/'.$identifier, ['quantity' => 1])->assertNotFound();
        }
    }

    public function test_leading_zero_identifiers_keep_the_existing_numeric_behavior(): void
    {
        $category = new Category(['name' => 'Peças', 'is_active' => true]);
        $category->id = 31;
        $this->mock(CategoryService::class)->shouldReceive('getPublicCategory')->once()->with(31)->andReturn($category);
        $this->getJson('/api/categories/00031')->assertOk()->assertJsonPath('data.id', 31);
    }

    public function test_the_largest_representable_identifier_is_forwarded_without_overflow(): void
    {
        $category = new Category(['name' => 'Peças', 'is_active' => true]);
        $category->id = PHP_INT_MAX;
        $this->mock(CategoryService::class)->shouldReceive('getPublicCategory')->once()->with(PHP_INT_MAX)->andReturn($category);
        $this->getJson('/api/categories/'.PHP_INT_MAX)->assertOk()->assertJsonPath('data.id', PHP_INT_MAX);
    }
}
