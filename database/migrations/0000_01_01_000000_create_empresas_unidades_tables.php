<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Base multi-tenant do SaaS:
     * - empresas: cada cliente do SaaS (rede/franquia de parques aquáticos)
     * - unidades: cada parque físico pertencente a uma empresa
     */
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('razao_social')->nullable();
            $table->string('cnpj', 18)->nullable()->unique();
            $table->string('email')->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('plano_saas', 30)->default('padrao'); // padrao, profissional, enterprise
            $table->string('status', 20)->default('ativo'); // ativo, suspenso, cancelado
            $table->date('trial_termina_em')->nullable();
            $table->json('configuracoes')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('unidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nome');
            $table->string('cnpj', 18)->nullable();
            $table->string('cep', 9)->nullable();
            $table->string('endereco')->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('complemento')->nullable();
            $table->string('bairro')->nullable();
            $table->string('cidade')->nullable();
            $table->string('uf', 2)->nullable();
            $table->string('telefone', 20)->nullable();
            $table->unsignedInteger('capacidade_maxima')->nullable();
            $table->string('status', 20)->default('ativo'); // ativo, inativo
            $table->timestamps();

            $table->index('empresa_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidades');
        Schema::dropIfExists('empresas');
    }
};
