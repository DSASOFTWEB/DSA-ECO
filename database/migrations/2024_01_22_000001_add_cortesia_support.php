<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Entrada "cortesia": a recepção presenteia N entradas (aniversário,
     * parceria, cortesia da casa) sem gerar venda nem receita — só o
     * registro de acesso, com QR de validação igual ao voucher online
     * (a pessoa pode usar depois, em outro dia, então precisa ser validado
     * na portaria como qualquer voucher que não foi usado na hora).
     */
    public function up(): void
    {
        Schema::table('acessos', function (Blueprint $table) {
            $table->foreignId('tipo_entrada_id')->nullable()->after('venda_item_id')->constrained('tipos_entrada')->nullOnDelete();
            $table->string('observacao')->nullable()->after('motivo_negado');
        });

        DB::statement('ALTER TABLE acessos DROP CHECK acessos_origem_check');
        DB::statement("ALTER TABLE acessos ADD CONSTRAINT acessos_origem_check CHECK (origem IN ('catraca','pdv_avulsa','pdv_plano','online','checkin_online','cortesia'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE acessos DROP CHECK acessos_origem_check');
        DB::statement("ALTER TABLE acessos ADD CONSTRAINT acessos_origem_check CHECK (origem IN ('catraca','pdv_avulsa','pdv_plano','online','checkin_online'))");

        Schema::table('acessos', function (Blueprint $table) {
            $table->dropForeign(['tipo_entrada_id']);
            $table->dropColumn(['tipo_entrada_id', 'observacao']);
        });
    }
};
