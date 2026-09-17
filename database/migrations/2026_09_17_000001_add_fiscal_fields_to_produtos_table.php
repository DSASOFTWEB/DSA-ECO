<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->string('tipo_item', 20)->default('produto')->after('nome');
            $table->string('ean', 14)->nullable()->after('sku');
            $table->string('unidade_comercial', 6)->default('UN')->after('ean');
            $table->string('imagem_url', 500)->nullable()->after('descricao');

            // NFC-e (mercadoria)
            $table->string('ncm', 8)->nullable()->after('imagem_url');
            $table->string('cest', 7)->nullable()->after('ncm');
            $table->string('cfop', 4)->nullable()->after('cest');
            $table->unsignedTinyInteger('origem')->nullable()->after('cfop');
            $table->string('cst_icms', 3)->nullable()->after('origem');
            $table->string('csosn', 4)->nullable()->after('cst_icms');
            $table->decimal('aliq_icms', 7, 4)->nullable()->after('csosn');
            $table->string('cst_pis', 2)->nullable()->after('aliq_icms');
            $table->decimal('aliq_pis', 7, 4)->nullable()->after('cst_pis');
            $table->string('cst_cofins', 2)->nullable()->after('aliq_pis');
            $table->decimal('aliq_cofins', 7, 4)->nullable()->after('cst_cofins');
            $table->string('cst_ipi', 2)->nullable()->after('aliq_cofins');
            $table->decimal('aliq_ipi', 7, 4)->nullable()->after('cst_ipi');
            $table->string('cod_beneficio', 10)->nullable()->after('aliq_ipi');

            // NFS-e Nacional (serviço)
            $table->string('codigo_servico_lc116', 10)->nullable()->after('cod_beneficio');
            $table->string('codigo_tributacao_municipal', 20)->nullable()->after('codigo_servico_lc116');
            $table->string('cnae_servico', 7)->nullable()->after('codigo_tributacao_municipal');
            $table->string('nbs', 9)->nullable()->after('cnae_servico');
            $table->decimal('aliq_iss', 7, 4)->nullable()->after('nbs');
            $table->boolean('iss_retido')->default(false)->after('aliq_iss');

            $table->index(['empresa_id', 'ean']);
            $table->index(['empresa_id', 'tipo_item']);
        });

        DB::statement("ALTER TABLE produtos ADD CONSTRAINT produtos_tipo_item_check CHECK (tipo_item IN ('produto', 'servico'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE produtos DROP CONSTRAINT produtos_tipo_item_check');

        Schema::table('produtos', function (Blueprint $table) {
            $table->dropIndex(['empresa_id', 'ean']);
            $table->dropIndex(['empresa_id', 'tipo_item']);
            $table->dropColumn([
                'tipo_item', 'ean', 'unidade_comercial', 'imagem_url',
                'ncm', 'cest', 'cfop', 'origem', 'cst_icms', 'csosn', 'aliq_icms',
                'cst_pis', 'aliq_pis', 'cst_cofins', 'aliq_cofins', 'cst_ipi', 'aliq_ipi', 'cod_beneficio',
                'codigo_servico_lc116', 'codigo_tributacao_municipal', 'cnae_servico', 'nbs', 'aliq_iss', 'iss_retido',
            ]);
        });
    }
};
