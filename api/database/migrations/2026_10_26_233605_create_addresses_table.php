<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('postal_code', 9);
            $table->string('street', 150);
            $table->string('number', 20);
            $table->string('complement', 100)->nullable();
            $table->string('city', 100);
            $table->char('state', 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
