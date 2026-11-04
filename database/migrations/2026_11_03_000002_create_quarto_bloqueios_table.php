<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Datas vendidas em outro canal (lidas do iCal do Booking.com etc.):
     * o iCal não traz hóspede, então não vira Hospedagem — vira bloqueio do
     * quarto, que a reserva/check-in do ERP respeita. "fim" é exclusivo
     * (dia da saída), igual a data_checkout_prevista.
     */
    public function up(): void
    {
        Schema::create('quarto_bloqueios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('quarto_id')->constrained('quartos')->cascadeOnDelete();
            $table->string('origem', 30)->default('ical'); // booking, airbnb, ical
            $table->string('uid', 255);
            $table->date('inicio');
            $table->date('fim');
            $table->string('resumo', 255)->nullable();
            // Datas já tinham reserva no ERP quando o canal vendeu: overbooking a resolver.
            $table->boolean('conflito')->default(false);
            // Recepção registrou o hóspede desta reserva do canal: a hospedagem
            // passa a ser quem ocupa o quarto (o bloqueio segue só como vínculo).
            $table->foreignId('hospedagem_id')->nullable()->constrained('hospedagens')->nullOnDelete();
            $table->timestamps();

            $table->unique(['quarto_id', 'uid']);
            $table->index(['quarto_id', 'inicio', 'fim']);
            $table->index('empresa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quarto_bloqueios');
    }
};
