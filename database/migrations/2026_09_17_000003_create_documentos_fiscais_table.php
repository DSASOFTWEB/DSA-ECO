<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('codigo_municipio_ibge', 7)->nullable()->after('aut_xml');
            $table->string('codigo_servico_hospedagem_lc116', 10)->nullable()->after('codigo_municipio_ibge');
        });

        Schema::create('documentos_fiscais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->nullable()->constrained('unidades')->nullOnDelete();
            $table->foreignId('hospedagem_id')->nullable()->constrained('hospedagens')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('emitido_por_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('modelo', 10); // nfce|nfe|nfse
            $table->string('origem', 40)->default('hospedagem');
            $table->unsignedTinyInteger('ambiente')->default(2);
            $table->string('status', 30)->default('rascunho');
            $table->unsignedInteger('serie')->nullable();
            $table->unsignedInteger('numero')->nullable();
            $table->string('chave', 60)->nullable();
            $table->string('protocolo', 60)->nullable();
            $table->decimal('valor_total', 12, 2)->default(0);
            $table->string('forma_pagamento', 40)->nullable();

            $table->longText('xml')->nullable();
            $table->longText('xml_protocolado')->nullable();
            $table->json('itens')->nullable();
            $table->json('retorno')->nullable();
            $table->text('mensagem_erro')->nullable();
            $table->timestamp('autorizado_em')->nullable();

            $table->timestamps();

            $table->index(['empresa_id', 'modelo', 'status']);
            $table->index(['hospedagem_id', 'modelo']);
        });

        DB::statement("ALTER TABLE documentos_fiscais ADD CONSTRAINT documentos_fiscais_modelo_check CHECK (modelo IN ('nfce', 'nfe', 'nfse'))");
        DB::statement("ALTER TABLE documentos_fiscais ADD CONSTRAINT documentos_fiscais_status_check CHECK (status IN ('rascunho', 'processando', 'autorizado', 'rejeitado', 'cancelado', 'erro'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE documentos_fiscais DROP CONSTRAINT documentos_fiscais_modelo_check');
        DB::statement('ALTER TABLE documentos_fiscais DROP CONSTRAINT documentos_fiscais_status_check');
        Schema::dropIfExists('documentos_fiscais');

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['codigo_municipio_ibge', 'codigo_servico_hospedagem_lc116']);
        });
    }
};
