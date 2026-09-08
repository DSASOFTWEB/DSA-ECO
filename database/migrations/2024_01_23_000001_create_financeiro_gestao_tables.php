<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contas_pagar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->nullable()->constrained('unidades')->nullOnDelete();
            $table->string('fornecedor', 150);
            $table->string('descricao', 255);
            $table->string('categoria', 60)->nullable()->comment('aluguel, energia, agua, manutencao, fornecedor, salario, imposto, outro');
            $table->decimal('valor', 15, 2);
            $table->date('data_vencimento');
            $table->date('data_pagamento')->nullable();
            $table->string('status', 20)->default('pendente')->comment('pendente, pago, atrasado, cancelado');
            $table->string('forma_pagamento', 30)->nullable();
            $table->foreignId('caixa_movimentacao_id')->nullable()->constrained('caixa_movimentacoes')->nullOnDelete();
            $table->text('observacoes')->nullable();
            $table->foreignId('criado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'status', 'data_vencimento']);
        });

        Schema::create('contas_receber', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->nullable()->constrained('unidades')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->string('pagador', 150)->comment('nome livre, usado quando não há cliente_id');
            $table->string('descricao', 255);
            $table->string('categoria', 60)->nullable()->comment('aluguel_espaco, patrocinio, reembolso, outro');
            $table->decimal('valor', 15, 2);
            $table->date('data_vencimento');
            $table->date('data_recebimento')->nullable();
            $table->string('status', 20)->default('pendente')->comment('pendente, recebido, atrasado, cancelado');
            $table->string('forma_pagamento', 30)->nullable();
            $table->foreignId('caixa_movimentacao_id')->nullable()->constrained('caixa_movimentacoes')->nullOnDelete();
            $table->text('observacoes')->nullable();
            $table->foreignId('criado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'status', 'data_vencimento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contas_receber');
        Schema::dropIfExists('contas_pagar');
    }
};
