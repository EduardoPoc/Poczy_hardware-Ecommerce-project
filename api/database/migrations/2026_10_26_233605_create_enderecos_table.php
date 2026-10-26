<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enderecos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->string('cep', 9);
            $table->string('logradouro', 150);
            $table->string('numero', 20);
            $table->string('complemento', 100)->nullable();
            $table->string('cidade', 100);
            $table->char('estado', 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enderecos');
    }
};
