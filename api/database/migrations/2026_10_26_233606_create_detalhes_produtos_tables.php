<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perifericos', function (Blueprint $table): void {
            $table->foreignId('produto_id')->primary()->constrained('produtos')->cascadeOnDelete();
            $table->string('tipo', 30)->index();
        });

        Schema::create('processadores', function (Blueprint $table): void {
            $table->foreignId('produto_id')->primary()->constrained('produtos')->cascadeOnDelete();
            $table->string('socket', 40)->index();
        });

        Schema::create('memorias_ram', function (Blueprint $table): void {
            $table->foreignId('produto_id')->primary()->constrained('produtos')->cascadeOnDelete();
            $table->unsignedInteger('velocidade_mhz')->index();
            $table->unsignedInteger('capacidade_gb')->index();
            $table->string('ddr', 10)->index();
        });

        Schema::create('placas_video', function (Blueprint $table): void {
            $table->foreignId('produto_id')->primary()->constrained('produtos')->cascadeOnDelete();
            $table->unsignedInteger('memoria_video_gb')->index();
        });

        Schema::create('placas_mae', function (Blueprint $table): void {
            $table->foreignId('produto_id')->primary()->constrained('produtos')->cascadeOnDelete();
            $table->string('formato', 30)->index();
            $table->string('ddr', 10)->index();
            $table->string('socket', 40)->index();
        });

        Schema::create('armazenamento', function (Blueprint $table): void {
            $table->foreignId('produto_id')->primary()->constrained('produtos')->cascadeOnDelete();
            $table->string('tipo', 30)->index();
            $table->unsignedInteger('velocidade_leitura_mbps')->nullable()->index();
            $table->unsignedInteger('velocidade_gravacao_mbps')->nullable()->index();
            $table->unsignedInteger('capacidade_gb')->index();
        });

        Schema::create('fontes', function (Blueprint $table): void {
            $table->foreignId('produto_id')->primary()->constrained('produtos')->cascadeOnDelete();
            $table->string('certificacao_80_plus', 30)->index();
            $table->string('modularidade', 20)->index();
        });

        Schema::create('gabinetes', function (Blueprint $table): void {
            $table->foreignId('produto_id')->primary()->constrained('produtos')->cascadeOnDelete();
            $table->string('formato_placa_mae', 30)->index();
        });

        Schema::create('refrigeracao', function (Blueprint $table): void {
            $table->foreignId('produto_id')->primary()->constrained('produtos')->cascadeOnDelete();
            $table->string('tipo', 30)->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refrigeracao');
        Schema::dropIfExists('gabinetes');
        Schema::dropIfExists('fontes');
        Schema::dropIfExists('armazenamento');
        Schema::dropIfExists('placas_mae');
        Schema::dropIfExists('placas_video');
        Schema::dropIfExists('memorias_ram');
        Schema::dropIfExists('processadores');
        Schema::dropIfExists('perifericos');
    }
};
