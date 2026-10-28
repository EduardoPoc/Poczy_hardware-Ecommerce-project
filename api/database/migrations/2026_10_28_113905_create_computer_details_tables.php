<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assembled_pcs', function (Blueprint $table): void {
            $table->foreignId('product_id')->primary()->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('storage_capacity_gb')->index();
            $table->boolean('has_dedicated_gpu')->index();
            $table->unsignedInteger('ram_capacity_gb')->index();
            $table->timestamps();
        });

        Schema::create('notebooks', function (Blueprint $table): void {
            $table->foreignId('product_id')->primary()->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('storage_capacity_gb')->index();
            $table->boolean('has_dedicated_gpu')->index();
            $table->unsignedInteger('ram_capacity_gb')->index();
            $table->decimal('screen_size_inches', 4, 1)->unsigned()->index();
            $table->string('screen_type', 30)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notebooks');
        Schema::dropIfExists('assembled_pcs');
    }
};
