<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias_produtos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nome');
            $table->timestamps();

            $table->index('empresa_id');
        });

        Schema::create('produtos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->nullable()->constrained('unidades')->nullOnDelete();
            $table->foreignId('categoria_id')->nullable()->constrained('categorias_produtos')->nullOnDelete();
            $table->string('nome');
            $table->string('sku', 60)->nullable();
            $table->text('descricao')->nullable();
            $table->decimal('preco_custo', 15, 2)->default(0);
            $table->decimal('preco_venda', 15, 2);
            $table->boolean('controla_estoque')->default(true);
            $table->integer('estoque_atual')->default(0);
            $table->integer('estoque_minimo')->default(0);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('empresa_id');
            $table->index('categoria_id');
            $table->index('ativo');
        });

        // SKU único por empresa quando informado. No Postgres isso era um índice
        // único PARCIAL (WHERE sku IS NOT NULL AND deleted_at IS NULL), que também
        // ignorava produtos soft-deleted. MySQL não suporta índice parcial, então
        // aqui é um índice único "cheio" em (empresa_id, sku) — SKUs nulos continuam
        // livres (MySQL, assim como o Postgres, trata cada NULL como distinto em
        // índice único), mas um SKU usado por um produto soft-deleted permanece
        // reservado até o produto ser restaurado/excluído em definitivo ou o SKU
        // ser limpo manualmente. StoreProdutoRequest/UpdateProdutoRequest validam
        // esse mesmo cenário antes de chegar ao banco, para dar um erro de
        // formulário em vez de uma exceção de integridade.
        Schema::table('produtos', function (Blueprint $table) {
            $table->unique(['empresa_id', 'sku'], 'produtos_empresa_sku_unique');
        });

        Schema::create('vendas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades')->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('vendedor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('caixa_id')->nullable(); // FK adicionada após criação da tabela caixas
            $table->decimal('valor_bruto', 15, 2);
            $table->decimal('desconto', 15, 2)->default(0);
            $table->decimal('valor_total', 15, 2);
            $table->string('status', 20)->default('pendente'); // pendente, pago, cancelado
            $table->string('forma_pagamento', 30)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('empresa_id');
            $table->index('unidade_id');
            $table->index('vendedor_id');
            $table->index('status');
        });

        Schema::create('venda_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venda_id')->constrained('vendas')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos')->restrictOnDelete();
            $table->integer('quantidade');
            $table->decimal('preco_unitario', 15, 2);
            $table->decimal('desconto', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();

            $table->index('venda_id');
            $table->index('produto_id');
        });

        DB::statement('ALTER TABLE venda_itens ADD CONSTRAINT venda_itens_quantidade_check CHECK (quantidade > 0)');

        Schema::create('movimentacoes_estoque', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produto_id')->constrained('produtos')->cascadeOnDelete();
            $table->string('tipo', 20); // entrada, saida, ajuste, venda, perda
            $table->integer('quantidade');
            $table->integer('quantidade_anterior');
            $table->integer('quantidade_atual');
            $table->string('motivo')->nullable();
            $table->nullableMorphs('referencia'); // ex: Venda que originou a baixa
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('produto_id');
        });

        Schema::create('comissoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('vendedor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('venda_id')->nullable()->constrained('vendas')->cascadeOnDelete();
            $table->foreignId('contrato_id')->nullable()->constrained('contratos')->cascadeOnDelete();
            $table->decimal('base_calculo', 15, 2);
            $table->decimal('percentual', 5, 2);
            $table->decimal('valor', 15, 2);
            $table->string('status', 20)->default('pendente'); // pendente, pago, cancelado
            $table->date('pago_em')->nullable();
            $table->timestamps();

            $table->index('vendedor_id');
            $table->index('status');
        });

        DB::statement('ALTER TABLE comissoes ADD CONSTRAINT comissoes_origem_check CHECK (venda_id IS NOT NULL OR contrato_id IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('comissoes');
        Schema::dropIfExists('movimentacoes_estoque');
        Schema::dropIfExists('venda_itens');
        Schema::dropIfExists('vendas');
        Schema::dropIfExists('produtos');
        Schema::dropIfExists('categorias_produtos');
    }
};
