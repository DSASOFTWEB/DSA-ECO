<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mensalidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->date('competencia'); // primeiro dia do mês de referência
            $table->decimal('valor_original', 15, 2);
            $table->decimal('desconto', 15, 2)->default(0);
            $table->decimal('acrescimo', 15, 2)->default(0);
            $table->decimal('valor_total', 15, 2);
            $table->date('data_vencimento');
            $table->date('data_pagamento')->nullable();
            $table->string('status', 20)->default('pendente'); // pendente, pago, atrasado, cancelado, isento
            $table->string('forma_pagamento', 30)->nullable();
            $table->string('gateway_transaction_id')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->unique(['contrato_id', 'competencia']);
            $table->index('empresa_id');
            $table->index('status');
            $table->index('data_vencimento');
        });

        Schema::create('cobrancas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mensalidade_id')->constrained('mensalidades')->cascadeOnDelete();
            $table->string('canal', 20); // whatsapp, email, sms
            $table->string('status', 20); // enviado, falhou, lido, respondido
            $table->unsignedSmallInteger('tentativa')->default(1);
            $table->text('mensagem')->nullable();
            $table->json('resposta_gateway')->nullable();
            $table->timestamp('enviado_em')->nullable();
            $table->timestamps();

            $table->index('mensalidade_id');
            $table->index(['canal', 'status']);
        });

        Schema::create('pagamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('mensalidade_id')->nullable()->constrained('mensalidades')->nullOnDelete();
            $table->foreignId('venda_id')->nullable()->constrained('vendas')->nullOnDelete();
            $table->string('gateway', 30); // mercadopago, dinheiro, cartao_credito, cartao_debito, pix_manual
            $table->string('gateway_payment_id')->nullable();
            $table->decimal('valor', 15, 2);
            $table->string('status', 20)->default('pendente'); // pendente, aprovado, recusado, estornado, em_processamento
            $table->string('metodo_pagamento', 30)->nullable(); // pix, boleto, cartao, dinheiro
            $table->json('payload')->nullable();
            $table->timestamp('pago_em')->nullable();
            $table->timestamps();

            $table->index('empresa_id');
            $table->index('status');
            $table->index('gateway_payment_id');
        });

        Schema::create('webhooks_mercadopago', function (Blueprint $table) {
            $table->id();
            $table->string('gateway_id')->nullable();
            $table->string('tipo', 40)->nullable(); // payment, merchant_order, etc.
            $table->json('payload');
            $table->string('status', 20)->default('recebido'); // recebido, processado, erro, ignorado
            $table->unsignedSmallInteger('tentativas')->default(0);
            $table->text('erro')->nullable();
            $table->timestamp('processado_em')->nullable();
            $table->timestamps();

            $table->index('gateway_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhooks_mercadopago');
        Schema::dropIfExists('pagamentos');
        Schema::dropIfExists('cobrancas');
        Schema::dropIfExists('mensalidades');
    }
};
