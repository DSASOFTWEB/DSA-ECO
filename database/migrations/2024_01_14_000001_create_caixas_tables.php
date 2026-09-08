<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caixas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades')->cascadeOnDelete();
            $table->foreignId('usuario_abertura_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('usuario_fechamento_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('data_abertura');
            $table->timestamp('data_fechamento')->nullable();
            $table->decimal('valor_abertura', 15, 2)->default(0);
            $table->decimal('valor_fechamento_informado', 15, 2)->nullable();
            $table->decimal('valor_fechamento_sistema', 15, 2)->nullable();
            $table->decimal('diferenca', 15, 2)->nullable();
            $table->string('status', 20)->default('aberto'); // aberto, fechado
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index('unidade_id');
            $table->index('status');
        });

        // Agora que "caixas" existe, conclui a FK pendente em vendas.caixa_id
        Schema::table('vendas', function (Blueprint $table) {
            $table->foreign('caixa_id')->references('id')->on('caixas')->nullOnDelete();
        });

        Schema::create('caixa_movimentacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caixa_id')->constrained('caixas')->cascadeOnDelete();
            $table->string('tipo', 10); // entrada, saida
            $table->string('categoria', 40); // venda, mensalidade, sangria, suprimento, despesa, outro
            $table->string('descricao')->nullable();
            $table->decimal('valor', 15, 2);
            $table->string('forma_pagamento', 30)->nullable();
            $table->nullableMorphs('referencia'); // Pagamento ou Venda de origem
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('caixa_id');
        });

        DB::statement("ALTER TABLE caixa_movimentacoes ADD CONSTRAINT caixa_movimentacoes_tipo_check CHECK (tipo IN ('entrada','saida'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('caixa_movimentacoes');
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropForeign(['caixa_id']);
        });
        Schema::dropIfExists('caixas');
    }
};
