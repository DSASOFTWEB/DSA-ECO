<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sincronização de calendário iCal por quarto (Booking.com, Airbnb...):
     * ical_token é o segredo do link que o Booking lê (exportação); a URL
     * de importação é o .ics do Booking que o ERP lê a cada 15 minutos.
     */
    public function up(): void
    {
        Schema::table('quartos', function (Blueprint $table) {
            $table->string('ical_token', 64)->nullable()->unique()->after('observacoes');
            $table->string('ical_importacao_url', 1000)->nullable()->after('ical_token');
            $table->timestamp('ical_sincronizado_em')->nullable()->after('ical_importacao_url');
            $table->string('ical_erro', 500)->nullable()->after('ical_sincronizado_em');
        });
    }

    public function down(): void
    {
        Schema::table('quartos', function (Blueprint $table) {
            $table->dropUnique(['ical_token']);
            $table->dropColumn(['ical_token', 'ical_importacao_url', 'ical_sincronizado_em', 'ical_erro']);
        });
    }
};
