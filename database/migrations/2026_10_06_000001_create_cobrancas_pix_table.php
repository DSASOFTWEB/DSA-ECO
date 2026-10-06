<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cobrança Pix dinâmica gerada no gateway da empresa (ex.: Itaú) durante o
 * recebimento no PDV. A venda só é criada quando o gateway confirma o
 * pagamento — até lá o carrinho validado fica guardado em dados_venda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobrancas_pix', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('caixa_id')->nullable()->constrained('caixas')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('venda_id')->nullable()->constrained('vendas')->nullOnDelete();
            $table->string('gateway', 30);
            $table->string('txid', 40);
            $table->decimal('valor', 10, 2);
            $table->string('status', 20)->default('pendente');
            $table->text('pix_copia_e_cola');
            $table->json('dados_venda');
            $table->json('payload')->nullable();
            $table->string('e2eid', 60)->nullable();
            $table->string('erro', 500)->nullable();
            $table->timestamp('expira_em')->nullable();
            $table->timestamp('pago_em')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'txid']);
            $table->index(['empresa_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobrancas_pix');
    }
};
