<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimentacoes_estoque', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('produto_id')->constrained('produtos')->restrictOnDelete();
            $table->unsignedInteger('quantidade');
            $table->enum('tipo', ['ENTRADA', 'SAIDA', 'AJUSTE']);
            $table->integer('variacao');
            $table->string('motivo', 150)->nullable();
            $table->dateTime('criado_em')->useCurrent();
            $table->index(['produto_id', 'criado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimentacoes_estoque');
    }
};
