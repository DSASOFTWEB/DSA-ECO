<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos', function (Blueprint $table) {
            $table->date('primeiro_vencimento')->nullable()->after('dia_vencimento');
            $table->decimal('valor_caucao', 15, 2)->default(0)->after('valor_mensal');
        });

        Schema::table('mensalidades', function (Blueprint $table) {
            $table->dropUnique('mensalidades_contrato_id_competencia_unique');
            $table->string('tipo', 20)->default('mensalidade')->after('empresa_id');
            $table->unique(['contrato_id', 'competencia', 'tipo']);
            $table->index(['empresa_id', 'tipo', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('mensalidades', function (Blueprint $table) {
            $table->dropIndex(['empresa_id', 'tipo', 'status']);
            $table->dropUnique(['contrato_id', 'competencia', 'tipo']);
            $table->dropColumn('tipo');
            $table->unique(['contrato_id', 'competencia']);
        });

        Schema::table('contratos', function (Blueprint $table) {
            $table->dropColumn(['primeiro_vencimento', 'valor_caucao']);
        });
    }
};
