<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permite dia de vencimento 1–31. Em meses curtos o MensalidadeService
 * já usa min(dia, daysInMonth), então 29/30/31 caem no último dia útil do mês.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE contratos DROP CHECK contratos_dia_vencimento_check');
        DB::statement('ALTER TABLE contratos ADD CONSTRAINT contratos_dia_vencimento_check CHECK (dia_vencimento BETWEEN 1 AND 31)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE contratos DROP CHECK contratos_dia_vencimento_check');
        DB::statement('ALTER TABLE contratos ADD CONSTRAINT contratos_dia_vencimento_check CHECK (dia_vencimento BETWEEN 1 AND 28)');
    }
};
