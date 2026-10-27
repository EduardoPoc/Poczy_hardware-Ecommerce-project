<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peripherals', function (Blueprint $table): void {
            $table->foreignId('product_id')->primary()->constrained('products')->cascadeOnDelete();
            $table->string('type', 30)->index();
        });

        Schema::create('processors', function (Blueprint $table): void {
            $table->foreignId('product_id')->primary()->constrained('products')->cascadeOnDelete();
            $table->string('socket', 40)->index();
        });

        Schema::create('ram_modules', function (Blueprint $table): void {
            $table->foreignId('product_id')->primary()->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('speed_mhz')->index();
            $table->unsignedInteger('capacity_gb')->index();
            $table->string('ddr', 10)->index();
        });

        Schema::create('graphics_cards', function (Blueprint $table): void {
            $table->foreignId('product_id')->primary()->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('video_memory_gb')->index();
        });

        Schema::create('motherboards', function (Blueprint $table): void {
            $table->foreignId('product_id')->primary()->constrained('products')->cascadeOnDelete();
            $table->string('form_factor', 30)->index();
            $table->string('ddr', 10)->index();
            $table->string('socket', 40)->index();
        });

        Schema::create('storage_devices', function (Blueprint $table): void {
            $table->foreignId('product_id')->primary()->constrained('products')->cascadeOnDelete();
            $table->string('type', 30)->index();
            $table->unsignedInteger('read_speed_mbps')->nullable()->index();
            $table->unsignedInteger('write_speed_mbps')->nullable()->index();
            $table->unsignedInteger('capacity_gb')->index();
        });

        Schema::create('power_supplies', function (Blueprint $table): void {
            $table->foreignId('product_id')->primary()->constrained('products')->cascadeOnDelete();
            $table->string('certification_80_plus', 30)->index();
            $table->string('modularity', 20)->index();
        });

        Schema::create('pc_cases', function (Blueprint $table): void {
            $table->foreignId('product_id')->primary()->constrained('products')->cascadeOnDelete();
            $table->string('motherboard_form_factor', 30)->index();
        });

        Schema::create('cooling_devices', function (Blueprint $table): void {
            $table->foreignId('product_id')->primary()->constrained('products')->cascadeOnDelete();
            $table->string('type', 30)->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cooling_devices');
        Schema::dropIfExists('pc_cases');
        Schema::dropIfExists('power_supplies');
        Schema::dropIfExists('storage_devices');
        Schema::dropIfExists('motherboards');
        Schema::dropIfExists('graphics_cards');
        Schema::dropIfExists('ram_modules');
        Schema::dropIfExists('processors');
        Schema::dropIfExists('peripherals');
    }
};
