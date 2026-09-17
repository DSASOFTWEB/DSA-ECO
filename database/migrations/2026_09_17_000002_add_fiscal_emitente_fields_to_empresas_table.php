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
            // Identificação fiscal do emitente
            $table->string('ie', 20)->nullable()->after('cnpj');
            $table->string('im', 20)->nullable()->after('ie');
            $table->string('cnae', 7)->nullable()->after('im');
            $table->string('regime_tributario', 30)->default('simples')->after('cnae');
            $table->string('aut_xml', 18)->nullable()->after('regime_tributario');

            // Ambiente SEFAZ: 1=produção, 2=homologação (padrão NFePHP)
            $table->unsignedTinyInteger('ambiente_nfe')->default(2)->after('aut_xml');

            // NFC-e CSC
            $table->string('csc', 60)->nullable()->after('ambiente_nfe');
            $table->string('csc_id', 10)->nullable()->after('csc');

            // Séries e numeração
            $table->unsignedInteger('numero_serie_nfe')->default(1)->after('csc_id');
            $table->unsignedInteger('numero_serie_nfce')->default(1)->after('numero_serie_nfe');
            $table->unsignedInteger('numero_serie_nfse')->default(1)->after('numero_serie_nfce');
            $table->unsignedInteger('numero_ultima_nfe_producao')->default(0)->after('numero_serie_nfse');
            $table->unsignedInteger('numero_ultima_nfe_homologacao')->default(0)->after('numero_ultima_nfe_producao');
            $table->unsignedInteger('numero_ultima_nfce_producao')->default(0)->after('numero_ultima_nfe_homologacao');
            $table->unsignedInteger('numero_ultima_nfce_homologacao')->default(0)->after('numero_ultima_nfce_producao');
            $table->unsignedInteger('numero_ultima_nfse')->default(0)->after('numero_ultima_nfce_homologacao');

            // NFS-e
            $table->string('nfse_provider', 30)->default('nacional_gov')->after('numero_ultima_nfse');
            $table->boolean('nfse_nacional_habilitado')->default(false)->after('nfse_provider');
            $table->text('token_nfse')->nullable()->after('nfse_nacional_habilitado');
            $table->string('token_ibpt', 120)->nullable()->after('token_nfse');

            // Cosmos (Bluesoft) — por empresa; vazio = fallback COSMOS_TOKEN do .env
            $table->string('bluesoft_token', 255)->nullable()->after('token_ibpt');

            $table->text('certificado_senha')->nullable()->after('bluesoft_token');
            $table->timestamp('certificado_validade')->nullable()->after('certificado_senha');

            $table->string('observacao_padrao_nfe', 500)->nullable()->after('certificado_validade');
            $table->string('observacao_padrao_nfce', 500)->nullable()->after('observacao_padrao_nfe');
        });

        // MEDIUMBLOB: Laravel Blueprint não expõe mediumBinary no MySQL Grammar desta versão.
        DB::statement('ALTER TABLE empresas ADD certificado_arquivo MEDIUMBLOB NULL AFTER bluesoft_token');

        DB::statement("ALTER TABLE empresas ADD CONSTRAINT empresas_regime_tributario_check CHECK (regime_tributario IN ('simples', 'simples_excesso', 'normal', 'mei'))");
        DB::statement('ALTER TABLE empresas ADD CONSTRAINT empresas_ambiente_nfe_check CHECK (ambiente_nfe IN (1, 2))');
        DB::statement("ALTER TABLE empresas ADD CONSTRAINT empresas_nfse_provider_check CHECK (nfse_provider IN ('nacional_gov', 'integranotas'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE empresas DROP CONSTRAINT empresas_regime_tributario_check');
        DB::statement('ALTER TABLE empresas DROP CONSTRAINT empresas_ambiente_nfe_check');
        DB::statement('ALTER TABLE empresas DROP CONSTRAINT empresas_nfse_provider_check');

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn([
                'ie', 'im', 'cnae', 'regime_tributario', 'aut_xml', 'ambiente_nfe',
                'csc', 'csc_id',
                'numero_serie_nfe', 'numero_serie_nfce', 'numero_serie_nfse',
                'numero_ultima_nfe_producao', 'numero_ultima_nfe_homologacao',
                'numero_ultima_nfce_producao', 'numero_ultima_nfce_homologacao',
                'numero_ultima_nfse',
                'nfse_provider', 'nfse_nacional_habilitado', 'token_nfse', 'token_ibpt',
                'bluesoft_token',
                'certificado_arquivo', 'certificado_senha', 'certificado_validade',
                'observacao_padrao_nfe', 'observacao_padrao_nfce',
            ]);
        });
    }
};
