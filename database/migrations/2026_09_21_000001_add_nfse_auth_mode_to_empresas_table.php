<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('nfse_auth_mode', 20)->default('certificado')->after('nfse_provider');
        });

        DB::statement("ALTER TABLE empresas ADD CONSTRAINT empresas_nfse_auth_mode_check CHECK (nfse_auth_mode IN ('certificado', 'usuario_senha'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE empresas DROP CONSTRAINT empresas_nfse_auth_mode_check');

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('nfse_auth_mode');
        });
    }
};
