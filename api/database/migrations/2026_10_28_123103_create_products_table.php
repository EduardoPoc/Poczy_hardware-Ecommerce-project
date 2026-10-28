<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->json('specifications')->nullable();
            $table->decimal('price', 10, 2)->unsigned();
            $table->decimal('cost', 10, 2)->unsigned()->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->string('image_url', 500)->nullable();
            $table->boolean('is_active')->default(true);

            $table->index('category_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
