<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 30);
            $table->string('payment_method', 40)->nullable();
            $table->decimal('products_total', 10, 2)->unsigned();
            $table->decimal('shipping_total', 10, 2)->unsigned();
            $table->decimal('total', 10, 2)->unsigned();
        });

        Schema::create('order_shipping_addresses', function (Blueprint $table): void {
            $table->foreignId('order_id')->primary()->constrained('orders')->cascadeOnDelete();
            $table->string('postal_code', 9);
            $table->string('street', 150);
            $table->string('number', 20);
            $table->string('complement', 100)->nullable();
            $table->string('city', 100);
            $table->char('state', 2);
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('category_name_snapshot', 100);
            $table->string('product_name_snapshot', 150);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2)->unsigned();
            $table->decimal('unit_cost', 10, 2)->unsigned()->nullable();
        });

        Schema::create('order_status_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('status', 30);
            $table->dateTime('changed_at')->useCurrent();
            $table->index(['order_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_history');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('order_shipping_addresses');
        Schema::dropIfExists('orders');
    }
};
