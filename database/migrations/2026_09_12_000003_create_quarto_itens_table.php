<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "O que tem hoje em cada quarto" — itens do estoque geral emprestados
     * em comodato (controle remoto, secador, jogo de toalha extra, etc).
     * Cada linha é o saldo ATUAL de um produto num quarto; o histórico de
     * quem emprestou/devolveu e quando já fica em `movimentacoes_estoque`
     * (tipo 'comodato'/'devolucao_comodato', referência = Quarto), sem
     * precisar duplicar isso aqui.
     */
    public function up(): void
    {
        Schema::create('quarto_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('quarto_id')->constrained('quartos')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos')->restrictOnDelete();
            $table->unsignedInteger('quantidade');
            $table->timestamps();

            $table->unique(['quarto_id', 'produto_id']);
            $table->index('empresa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quarto_itens');
    }
};
