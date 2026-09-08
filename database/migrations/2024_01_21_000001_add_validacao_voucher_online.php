<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vouchers comprados/gerados pelo link público (origem "online" ou
     * "checkin_online") só contam como entrada de verdade quando alguém da
     * recepção escaneia o QR na portaria — antes disso é só um ingresso
     * emitido, não uma entrada confirmada. Isso fecha a brecha de fraude de
     * um mesmo voucher (print ou print da tela) ser usado por mais de uma
     * pessoa: o código só pode ser validado uma vez.
     */
    public function up(): void
    {
        Schema::table('acessos', function (Blueprint $table) {
            $table->string('codigo_validacao', 64)->nullable()->unique()->after('origem');
            $table->timestamp('validado_em')->nullable()->after('motivo_negado');
            $table->foreignId('validado_por_id')->nullable()->after('validado_em')->constrained('users')->nullOnDelete();
            // Qual item da venda gerou esta entrada — necessário porque uma
            // venda pode ter mais de um tipo de entrada (ex.: 2 adultos + 1
            // criança na mesma compra) e cada Acesso é uma pessoa só; sem
            // isso não dava pra saber, no voucher, qual tipo cada um é.
            $table->foreignId('venda_item_id')->nullable()->after('venda_id')->constrained('venda_itens')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('acessos', function (Blueprint $table) {
            $table->dropForeign(['validado_por_id']);
            $table->dropForeign(['venda_item_id']);
            $table->dropColumn(['codigo_validacao', 'validado_em', 'validado_por_id', 'venda_item_id']);
        });
    }
};
