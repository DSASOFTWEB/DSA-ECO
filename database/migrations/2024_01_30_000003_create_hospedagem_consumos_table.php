<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospedagem_consumos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospedagem_id')->constrained('hospedagens')->cascadeOnDelete();
            // Nulo = item avulso (taxa/serviço sem controle de estoque) —
            // nesse caso 'descricao' é obrigatória (ver StoreConsumoRequest).
            $table->foreignId('produto_id')->nullable()->constrained('produtos')->restrictOnDelete();
            $table->string('descricao')->nullable();
            $table->unsignedInteger('quantidade')->default(1);
            $table->decimal('valor_unitario', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->foreignId('registrado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('hospedagem_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospedagem_consumos');
    }
};
