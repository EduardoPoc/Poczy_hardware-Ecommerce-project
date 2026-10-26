<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->string('status', 30);
            $table->string('forma_pagamento', 40)->nullable();
            $table->decimal('valor_produtos', 10, 2)->unsigned();
            $table->decimal('valor_frete', 10, 2)->unsigned();
            $table->decimal('valor_total', 10, 2)->unsigned();
            $table->dateTime('criado_em')->useCurrent();
            $table->index(['usuario_id', 'criado_em']);
            $table->index(['status', 'criado_em']);
        });

        Schema::create('endereco_entrega_pedido', function (Blueprint $table): void {
            $table->foreignId('pedido_id')->primary()->constrained('pedidos')->cascadeOnDelete();
            $table->string('cep', 9);
            $table->string('logradouro', 150);
            $table->string('numero', 20);
            $table->string('complemento', 100)->nullable();
            $table->string('cidade', 100);
            $table->char('estado', 2);
        });

        Schema::create('itens_pedido', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->foreignId('produto_id')->nullable()->constrained('produtos')->nullOnDelete();
            $table->foreignId('categoria_id')->nullable()->constrained('categorias')->nullOnDelete();
            $table->string('categoria_nome_snapshot', 100);
            $table->string('nome_produto_snapshot', 150);
            $table->unsignedInteger('quantidade');
            $table->decimal('preco_unitario', 10, 2)->unsigned();
            $table->decimal('custo_unitario', 10, 2)->unsigned()->nullable();
        });

        Schema::create('historico_status_pedido', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete();
            $table->string('status', 30);
            $table->dateTime('alterado_em')->useCurrent();
            $table->index(['pedido_id', 'alterado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historico_status_pedido');
        Schema::dropIfExists('itens_pedido');
        Schema::dropIfExists('endereco_entrega_pedido');
        Schema::dropIfExists('pedidos');
    }
};
