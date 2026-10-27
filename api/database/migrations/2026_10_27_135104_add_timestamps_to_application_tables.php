<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = [
        'users',
        'password_change_history',
        'password_reset_tokens',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'addresses',
        'categories',
        'brands',
        'products',
        'orders',
        'order_shipping_addresses',
        'order_items',
        'order_status_history',
        'peripherals',
        'processors',
        'ram_modules',
        'graphics_cards',
        'motherboards',
        'storage_devices',
        'power_supplies',
        'pc_cases',
        'cooling_devices',
        'stock_movements',
    ];

    /** @var array<string, list<list<string>>> */
    private const INDEXES = [
        'orders' => [
            ['user_id', 'created_at'],
            ['status', 'created_at'],
        ],
        'stock_movements' => [
            ['product_id', 'created_at'],
        ],
    ];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            $hasCreatedAt = Schema::hasColumn($name, 'created_at');
            $hasUpdatedAt = Schema::hasColumn($name, 'updated_at');

            if ($hasCreatedAt && $hasUpdatedAt) {
                continue;
            }

            Schema::table($name, function (Blueprint $table) use ($name, $hasCreatedAt, $hasUpdatedAt): void {
                if (! $hasCreatedAt && ! $hasUpdatedAt && ! in_array($name, ['jobs', 'job_batches'], true)) {
                    $table->timestamps();

                    return;
                }

                if (! $hasCreatedAt) {
                    // Laravel queue drivers store creation dates as Unix timestamps.
                    if ($name === 'jobs') {
                        $table->unsignedInteger('created_at');
                    } elseif ($name === 'job_batches') {
                        $table->integer('created_at');
                    } else {
                        $table->timestamp('created_at')->nullable();
                    }
                }

                if (! $hasUpdatedAt) {
                    $table->timestamp('updated_at')->nullable();
                }
            });
        }

        foreach (self::INDEXES as $name => $indexes) {
            foreach ($indexes as $columns) {
                if (! Schema::hasIndex($name, $columns)) {
                    Schema::table($name, function (Blueprint $table) use ($columns): void {
                        $table->index($columns);
                    });
                }
            }
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::INDEXES, true) as $name => $indexes) {
            foreach (array_reverse($indexes) as $columns) {
                if (Schema::hasIndex($name, $columns)) {
                    Schema::table($name, function (Blueprint $table) use ($columns): void {
                        $table->dropIndex($columns);
                    });
                }
            }
        }

        foreach (array_reverse(self::TABLES) as $name) {
            $columns = array_values(array_filter(
                ['created_at', 'updated_at'],
                fn (string $column): bool => Schema::hasColumn($name, $column),
            ));

            if ($columns !== []) {
                Schema::table($name, function (Blueprint $table) use ($columns): void {
                    $table->dropColumn($columns);
                });
            }
        }
    }
};
