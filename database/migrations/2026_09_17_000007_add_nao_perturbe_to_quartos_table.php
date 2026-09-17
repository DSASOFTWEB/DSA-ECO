<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quartos', function (Blueprint $table) {
            $table->boolean('nao_perturbe')->default(false)->after('precisa_limpeza');
        });
    }

    public function down(): void
    {
        Schema::table('quartos', function (Blueprint $table) {
            $table->dropColumn('nao_perturbe');
        });
    }
};
