<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('codigo_tributacao_municipal_hospedagem', 20)
                ->nullable()
                ->after('codigo_servico_hospedagem_lc116');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('codigo_tributacao_municipal_hospedagem');
        });
    }
};
