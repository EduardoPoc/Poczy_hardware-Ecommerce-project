<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $unavailable = DB::table('products')->select('products.id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where(function (Builder $query): void {
                $query->where('products.is_active', false)->orWhere('products.stock', '<', 1)
                    ->orWhere('categories.is_active', false);
            });
        DB::table('cart_items')->whereIn('product_id', $unavailable)->delete();
    }

    /**
     * Produtos removidos dos carrinhos precisam ser adicionados novamente pelo cliente.
     */
    public function down(): void {}
};
