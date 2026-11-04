<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Services\StockService;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StockMovementSchemaUpgradeTest extends TestCase
{
    use DatabaseMigrations;

    public function test_legacy_stock_history_is_preserved_and_seeding_and_signed_adjustments_succeed(): void
    {
        DB::statement('ALTER TABLE stock_movements MODIFY quantity INT UNSIGNED NOT NULL, ADD `change` INT NOT NULL');
        $category = Category::create(['name' => 'Categoria legada']);
        $product = $category->products()->create(['name' => 'Produto legado', 'price' => '10.00', 'stock' => 8]);
        $history = [
            ['id' => 11, 'type' => 'INBOUND', 'quantity' => 8, 'change' => 8],
            ['id' => 12, 'type' => 'OUTBOUND', 'quantity' => 3, 'change' => -3],
            ['id' => 13, 'type' => 'ADJUSTMENT', 'quantity' => 2, 'change' => -2],
            ['id' => 14, 'type' => 'ADJUSTMENT', 'quantity' => 5, 'change' => 5],
        ];
        foreach ($history as $movement) {
            DB::table('stock_movements')->insert($movement + [
                'product_id' => $product->id,
                'reason' => 'Movimentação legada',
                'created_at' => '2026-10-28 12:00:00',
                'updated_at' => '2026-10-28 12:00:00',
            ]);
        }

        $migration = require database_path('migrations/2026_11_03_110024_normalize_legacy_stock_movements.php');
        $migration->up();

        $this->assertFalse(Schema::hasColumn('stock_movements', 'change'));
        $this->assertDatabaseCount('stock_movements', 4);
        foreach ([11 => 8, 12 => 3, 13 => -2, 14 => 5] as $id => $quantity) {
            $this->assertDatabaseHas('stock_movements', [
                'id' => $id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'reason' => 'Movimentação legada',
                'created_at' => '2026-10-28 12:00:00',
                'updated_at' => '2026-10-28 12:00:00',
            ]);
        }
        $this->assertSame(8, $product->fresh()->stock);

        $this->seed(ProductSeeder::class);
        $this->assertDatabaseCount('products', 49);
        $this->assertDatabaseCount('stock_movements', 51);
        $movement = app(StockService::class)->create($product->id, ['type' => 'ADJUSTMENT', 'quantity' => -1]);
        $this->assertSame(-1, $movement->quantity);
        $this->assertSame(7, $product->fresh()->stock);
    }

    public function test_current_schema_and_signed_history_are_unchanged_when_the_migration_runs(): void
    {
        $category = Category::create(['name' => 'Categoria atual']);
        $product = $category->products()->create(['name' => 'Produto atual', 'price' => '10.00', 'stock' => 3]);
        $movement = $product->stockMovements()->create(['type' => 'ADJUSTMENT', 'quantity' => -2]);
        $history = $movement->fresh()->getAttributes();

        $migration = require database_path('migrations/2026_11_03_110024_normalize_legacy_stock_movements.php');
        $migration->up();
        $migration->down();

        $this->assertFalse(Schema::hasColumn('stock_movements', 'change'));
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertSame($history, $movement->fresh()->getAttributes());
        $this->assertSame(3, $product->fresh()->stock);
    }
}
