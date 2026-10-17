<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formas_pagamento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nome', 60);
            $table->string('tipo', 30); // chave de App\Models\FormaPagamento::TIPOS
            $table->boolean('integrado')->default(false); // Pix: gera QR Code no gateway da empresa
            $table->boolean('ativo')->default(true);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();

            $table->index(['empresa_id', 'ativo']);
        });

        Schema::table('cobrancas_pix', function (Blueprint $table) {
            // Cobrança gerada no fechamento de mesa/comanda ou no check-out da
            // hospedagem (em vez de venda do PDV).
            $table->foreignId('atendimento_id')->nullable()->after('venda_id')->constrained('atendimentos')->nullOnDelete();
            $table->foreignId('hospedagem_id')->nullable()->after('atendimento_id')->constrained('hospedagens')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cobrancas_pix', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hospedagem_id');
            $table->dropConstrainedForeignId('atendimento_id');
        });

        Schema::dropIfExists('formas_pagamento');
    }
};
