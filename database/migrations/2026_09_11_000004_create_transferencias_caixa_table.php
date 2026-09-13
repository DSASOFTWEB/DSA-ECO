<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transferencias_caixa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('caixa_origem_id')->constrained('caixas')->restrictOnDelete();
            $table->foreignId('caixa_destino_id')->constrained('caixas')->restrictOnDelete();
            $table->decimal('valor', 15, 2);
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->string('observacao')->nullable();
            $table->foreignId('movimentacao_saida_id')->constrained('caixa_movimentacoes')->restrictOnDelete();
            $table->foreignId('movimentacao_entrada_id')->constrained('caixa_movimentacoes')->restrictOnDelete();
            $table->timestamps();

            $table->index('empresa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transferencias_caixa');
    }
};
