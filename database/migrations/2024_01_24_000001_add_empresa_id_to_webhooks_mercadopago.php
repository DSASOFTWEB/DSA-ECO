<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada empresa pode ter sua própria conta de Mercado Pago — o webhook
     * chega numa URL compartilhada (o Mercado Pago não sabe de "empresas"),
     * então precisamos descobrir de qual empresa é o pagamento batendo a
     * assinatura contra o segredo de cada uma (ver
     * MercadoPagoWebhookController). Guardar aqui evita repetir essa busca
     * dentro do Job assíncrono.
     */
    public function up(): void
    {
        Schema::table('webhooks_mercadopago', function (Blueprint $table) {
            $table->foreignId('empresa_id')->nullable()->after('id')->constrained('empresas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('webhooks_mercadopago', function (Blueprint $table) {
            $table->dropConstrainedForeignId('empresa_id');
        });
    }
};
