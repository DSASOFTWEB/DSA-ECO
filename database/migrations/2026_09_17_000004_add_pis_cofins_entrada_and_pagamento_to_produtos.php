<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            // PIS/COFINS de entrada (compras) — saída continua em cst_pis/cst_cofins
            $table->string('cst_pis_entrada', 2)->nullable()->after('cod_beneficio');
            $table->decimal('aliq_pis_entrada', 7, 4)->nullable()->after('cst_pis_entrada');
            $table->string('cst_cofins_entrada', 2)->nullable()->after('aliq_pis_entrada');
            $table->decimal('aliq_cofins_entrada', 7, 4)->nullable()->after('cst_cofins_entrada');

            // Forma de pagamento padrão (código tPag da NF-e/NFC-e)
            $table->string('forma_pagamento_fiscal', 2)->nullable()->after('aliq_cofins_entrada');
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropColumn([
                'cst_pis_entrada', 'aliq_pis_entrada',
                'cst_cofins_entrada', 'aliq_cofins_entrada',
                'forma_pagamento_fiscal',
            ]);
        });
    }
};
