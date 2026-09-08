<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades')->cascadeOnDelete();
            $table->string('nome');
            $table->string('status', 20)->default('ativo'); // ativo, inativo
            $table->timestamps();

            $table->index('empresa_id');
            $table->index('unidade_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terminais');
    }
};
