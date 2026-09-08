<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Uma linha cobre o ciclo inteiro de uma estadia: reservado (data
     * futura, quarto bloqueado) -> hospedado (check-in feito) ->
     * finalizado (check-out, já cobrado) — ou cancelado antes do check-in.
     * Mesma ideia da Venda "pendente -> paga" já usada no checkout online
     * (VendaService::criarVendaOnlinePendente/confirmarPagamentoOnline).
     */
    public function up(): void
    {
        Schema::create('hospedagens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades')->cascadeOnDelete();
            $table->foreignId('quarto_id')->constrained('quartos')->restrictOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->unsignedTinyInteger('quantidade_hospedes')->default(1);
            // Snapshot do valor do quarto no momento da reserva — se o
            // quarto mudar de preço depois, a estadia já reservada não muda.
            $table->decimal('valor_diaria', 10, 2);
            $table->date('data_checkin_prevista');
            $table->date('data_checkout_prevista');
            $table->timestamp('data_checkin_real')->nullable();
            $table->timestamp('data_checkout_real')->nullable();
            $table->decimal('valor_total', 10, 2)->nullable();
            $table->decimal('desconto', 10, 2)->default(0);
            $table->string('forma_pagamento', 30)->nullable();
            $table->string('status', 20)->default('reservado'); // reservado, hospedado, finalizado, cancelado
            $table->text('observacoes')->nullable();
            $table->foreignId('caixa_id')->nullable()->constrained('caixas')->nullOnDelete();
            $table->foreignId('registrado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('empresa_id');
            $table->index('quarto_id');
            $table->index('cliente_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospedagens');
    }
};
