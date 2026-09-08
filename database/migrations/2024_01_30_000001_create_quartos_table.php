<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quartos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades')->cascadeOnDelete();
            $table->string('numero', 30);
            $table->unsignedTinyInteger('capacidade_maxima')->default(1);
            $table->decimal('valor_diaria', 10, 2);
            $table->string('status', 20)->default('ativo'); // ativo, manutencao, inativo
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['unidade_id', 'numero']);
            $table->index('empresa_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quartos');
    }
};
