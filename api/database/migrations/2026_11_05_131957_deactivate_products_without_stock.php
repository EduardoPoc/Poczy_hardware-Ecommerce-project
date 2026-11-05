<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')->where('stock', '<', 1)->where('is_active', true)
            ->update(['is_active' => false]);
    }

    /**
     * Preserve deactivation on rollback to avoid publishing products without stock.
     */
    public function down(): void {}
};
