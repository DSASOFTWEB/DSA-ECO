<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cidades', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 120);
            $table->char('uf', 2);
            $table->string('codigo', 7);
            $table->timestamps();

            $table->unique('codigo');
            $table->index(['uf', 'nome']);
            $table->index('nome');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cidades');
    }
};
