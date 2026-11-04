<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('stock_movements', 'change')) {
            return;
        }

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->integer('quantity')->change();
        });

        DB::table('stock_movements')->where('type', 'ADJUSTMENT')->update([
            'quantity' => DB::raw('`change`'),
        ]);

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropColumn('change');
        });
    }

    /**
     * Keep the current schema already defined by the original create migration.
     */
    public function down(): void {}
};
