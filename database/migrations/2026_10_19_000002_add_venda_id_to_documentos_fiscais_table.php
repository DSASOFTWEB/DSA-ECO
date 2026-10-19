<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NFC-e emitida na finalização da venda (PDV, mesa e comanda), além da
 * hospedagem que já existia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos_fiscais', function (Blueprint $table) {
            $table->foreignId('venda_id')->nullable()->after('hospedagem_id')->constrained('vendas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documentos_fiscais', function (Blueprint $table) {
            $table->dropConstrainedForeignId('venda_id');
        });
    }
};
