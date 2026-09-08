<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Prazo de validade opcional de uma entrada de cortesia — depois dessa
     * data o voucher não pode mais ser validado na portaria (ver
     * AcessoService::validarVoucher). Nulo = sem prazo (comportamento de
     * sempre, mantido pra vendas avulsas/check-in, que não usam este campo).
     */
    public function up(): void
    {
        Schema::table('acessos', function (Blueprint $table) {
            $table->date('validade_ate')->nullable()->after('observacao');
        });
    }

    public function down(): void
    {
        Schema::table('acessos', function (Blueprint $table) {
            $table->dropColumn('validade_ate');
        });
    }
};
