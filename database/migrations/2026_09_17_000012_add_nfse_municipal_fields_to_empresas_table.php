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
            $table->string('nfse_ws_user', 120)->nullable()->after('token_nfse');
            $table->text('nfse_ws_senha')->nullable()->after('nfse_ws_user');
            $table->string('nfse_ws_chave_acesso', 255)->nullable()->after('nfse_ws_senha');
        });

        DB::statement('ALTER TABLE empresas DROP CONSTRAINT empresas_nfse_provider_check');
        DB::statement("ALTER TABLE empresas ADD CONSTRAINT empresas_nfse_provider_check CHECK (nfse_provider IN ('nacional_gov', 'integranotas', 'municipio'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE empresas DROP CONSTRAINT empresas_nfse_provider_check');
        DB::statement("ALTER TABLE empresas ADD CONSTRAINT empresas_nfse_provider_check CHECK (nfse_provider IN ('nacional_gov', 'integranotas'))");

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['nfse_ws_user', 'nfse_ws_senha', 'nfse_ws_chave_acesso']);
        });
    }
};
