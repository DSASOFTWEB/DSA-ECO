<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipos_entrada', function (Blueprint $table) {
            // Quando marcado, esta opção no link público não cobra Pix — em
            // vez disso pede CPF/nome e verifica o plano do cliente, igual
            // ao check-in feito na recepção (ver AcessoService::registrarEntradaPlano).
            $table->boolean('eh_plano')->default(false)->after('valor');
        });

        DB::statement('ALTER TABLE acessos DROP CHECK acessos_origem_check');
        DB::statement("ALTER TABLE acessos ADD CONSTRAINT acessos_origem_check CHECK (origem IN ('catraca','pdv_avulsa','pdv_plano','online','checkin_online'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE acessos DROP CHECK acessos_origem_check');
        DB::statement("ALTER TABLE acessos ADD CONSTRAINT acessos_origem_check CHECK (origem IN ('catraca','pdv_avulsa','pdv_plano','online'))");

        Schema::table('tipos_entrada', function (Blueprint $table) {
            $table->dropColumn('eh_plano');
        });
    }
};
