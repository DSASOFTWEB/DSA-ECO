<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fechamento mensal das NFC-e: pacote ZIP com XMLs, DANFEs em PDF e resumo
 * (para o contador), gerado sob demanda e reenviável por e-mail/WhatsApp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fechamentos_fiscais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('modelo', 10)->default('nfce');
            $table->date('competencia'); // primeiro dia do mês
            $table->string('status', 20)->default('processando'); // processando | pronto | erro
            $table->unsignedInteger('quantidade')->default(0);
            $table->decimal('valor_total', 14, 2)->default(0);
            $table->string('arquivo_path', 500)->nullable();
            $table->string('arquivo_nome', 200)->nullable();
            $table->text('erro')->nullable();
            $table->foreignId('gerado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('gerado_em')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'modelo', 'competencia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fechamentos_fiscais');
    }
};
