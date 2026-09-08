<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Percentual de comissão próprio do vendedor — nulo mantém o padrão da
     * empresa (ver ComissaoService::PERCENTUAL_PADRAO_VENDA/CONTRATO), que
     * até aqui era fixo pra todo mundo.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('percentual_comissao', 5, 2)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('percentual_comissao');
        });
    }
};
