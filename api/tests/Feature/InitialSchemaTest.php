<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InitialSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrations_create_the_current_schema_on_mysql(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertSame('ecommerce_test', DB::connection()->getDatabaseName());
        $tables = Schema::getTableListing(null, false);
        $this->assertCount(21, $tables);
        foreach (['users', 'addresses', 'password_change_histories', 'categories', 'brands', 'products', 'orders',
            'order_items', 'order_shipping_addresses', 'order_status_histories', 'stock_movements', 'carts', 'cart_items'] as $table) {
            $this->assertContains($table, $tables);
        }
        $this->assertFalse(Schema::hasColumn('stock_movements', 'change'));
        $this->assertSame('json', Schema::getColumnType('products', 'specifications'));
        $this->assertStringContainsString('decimal(10,2)', Schema::getColumnType('products', 'price', true));
    }

    public function test_user_defaults_password_hash_and_serialization_match_the_model(): void
    {
        $user = User::create(['name' => 'Cliente', 'email' => 'schema@example.com', 'password' => 'SenhaTeste!2026'])->refresh();
        $this->assertSame('CUSTOMER', $user->role);
        $this->assertTrue(Hash::check('SenhaTeste!2026', $user->password));
        $this->assertFalse($user->isFillable('role'));
        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertArrayNotHasKey('remember_token', $user->toArray());
        $this->assertNotNull($user->created_at);
        $this->assertNotNull($user->updated_at);
    }

    public function test_mysql_rejects_products_with_missing_foreign_keys(): void
    {
        $this->expectException(QueryException::class);
        Product::create(['category_id' => 999999, 'name' => 'Inválido', 'price' => '10.00']);
    }

    public function test_mysql_accepts_signed_stock_adjustments_and_enforces_unique_category_names(): void
    {
        $category = Category::create(['name' => 'Memórias']);
        $product = $category->products()->create(['name' => 'Memória', 'price' => '249.90', 'stock' => 3]);
        $product->stockMovements()->create(['type' => 'ADJUSTMENT', 'quantity' => -2]);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $product->id, 'quantity' => -2]);
        $this->expectException(QueryException::class);
        Category::create(['name' => 'Memórias']);
    }
}
