<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * De onde a venda veio — base dos relatórios por canal:
 *   pdv    = frente de caixa (inclui Pix QR do PDV)
 *   mesa   = fechamento de mesa/comanda
 *   online = link público de compra (autoatendimento)
 * Vendas antigas são classificadas pelo histórico: as de mesa estão
 * ligadas a um atendimento; as online foram criadas sem vendedor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->string('origem', 20)->default('pdv')->after('caixa_id');
            $table->index(['empresa_id', 'origem', 'created_at']);
        });

        DB::table('vendas')->whereNull('vendedor_id')->update(['origem' => 'online']);
        DB::table('vendas')
            ->whereIn('id', DB::table('atendimentos')->whereNotNull('venda_id')->select('venda_id'))
            ->update(['origem' => 'mesa']);
    }

    public function down(): void
    {
        Schema::table('vendas', function (Blueprint $table) {
            $table->dropIndex(['empresa_id', 'origem', 'created_at']);
            $table->dropColumn('origem');
        });
    }
};
