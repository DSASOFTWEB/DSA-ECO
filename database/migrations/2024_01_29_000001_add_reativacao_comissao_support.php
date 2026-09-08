<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->timestamp('reativado_em')->nullable()->after('cancelado_em');
        });

        Schema::table('comissoes', function (Blueprint $table) {
            // 'venda', 'contrato' (fechamento de plano novo) ou 'reativacao'
            // (contrato cancelado que voltou a ficar ativo) — sem isso não
            // dava pra saber, só pelo contrato_id, se a comissão foi de uma
            // venda nova ou de uma reativação (as duas usam o mesmo FK).
            $table->string('tipo', 20)->nullable()->after('contrato_id');
        });

        // Backfill das linhas existentes: até aqui só existiam os dois tipos
        // originais, e dava pra inferir com certeza qual FK estava preenchido.
        DB::statement("UPDATE comissoes SET tipo = IF(venda_id IS NOT NULL, 'venda', 'contrato') WHERE tipo IS NULL");

        Schema::table('users', function (Blueprint $table) {
            // Percentual próprio pra comissão de REATIVAÇÃO de contrato
            // cancelado — separado do percentual_comissao "padrão" (venda/
            // contrato novo) porque a prática é reativação valer mais.
            $table->decimal('percentual_comissao_reativacao', 5, 2)->nullable()->after('percentual_comissao');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('percentual_comissao_reativacao');
        });

        Schema::table('comissoes', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });

        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn('reativado_em');
        });
    }
};
