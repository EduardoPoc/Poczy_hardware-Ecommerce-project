<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('nome', 100);
            $table->text('descricao')->nullable();
            $table->boolean('ativa')->default(true);
        });

        Schema::create('marcas', function (Blueprint $table): void {
            $table->id();
            $table->string('nome', 100)->unique();
        });

        Schema::create('produtos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('categoria_id')->constrained('categorias')->restrictOnDelete();
            $table->foreignId('marca_id')->nullable()->constrained('marcas')->nullOnDelete();
            $table->string('nome', 150);
            $table->text('descricao')->nullable();
            $table->decimal('preco', 10, 2)->unsigned();
            $table->decimal('custo', 10, 2)->unsigned()->nullable();
            $table->unsignedInteger('estoque')->default(0);
            $table->string('imagem_url', 500)->nullable();
            $table->boolean('ativo')->default(true);
            $table->dateTime('criado_em')->useCurrent();
            $table->index(['categoria_id', 'preco']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produtos');
        Schema::dropIfExists('marcas');
        Schema::dropIfExists('categorias');
    }
};
