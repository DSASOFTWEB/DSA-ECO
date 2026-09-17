<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->decimal('aliquota_iss_hospedagem', 7, 4)
                ->nullable()
                ->after('codigo_servico_hospedagem_lc116');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('aliquota_iss_hospedagem');
        });
    }
};
