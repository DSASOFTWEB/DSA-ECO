<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Série de NFC-e por terminal (vários caixas emitindo ao mesmo tempo, cada
 * um com numeração própria). Terminal sem série usa a série padrão da
 * empresa (empresas.numero_serie_nfce). Uma série pertence a um só dono,
 * então o número da nota nunca se repete entre caixas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terminais', function (Blueprint $table) {
            $table->unsignedSmallInteger('serie_nfce')->nullable()->after('status');
            $table->unsignedInteger('numero_ultima_nfce_producao')->default(0)->after('serie_nfce');
            $table->unsignedInteger('numero_ultima_nfce_homologacao')->default(0)->after('numero_ultima_nfce_producao');
            $table->unique(['empresa_id', 'serie_nfce']);
        });
    }

    public function down(): void
    {
        Schema::table('terminais', function (Blueprint $table) {
            $table->dropUnique(['empresa_id', 'serie_nfce']);
            $table->dropColumn(['serie_nfce', 'numero_ultima_nfce_producao', 'numero_ultima_nfce_homologacao']);
        });
    }
};
