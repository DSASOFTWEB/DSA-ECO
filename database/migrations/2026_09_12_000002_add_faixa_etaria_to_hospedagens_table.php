<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hospedagens', function (Blueprint $table) {
            $table->unsignedTinyInteger('quantidade_adultos')->default(1)->after('quantidade_hospedes');
            $table->unsignedTinyInteger('quantidade_criancas')->default(0)->after('quantidade_adultos');
            $table->unsignedTinyInteger('quantidade_isentos')->default(0)->after('quantidade_criancas');
        });
    }

    public function down(): void
    {
        Schema::table('hospedagens', function (Blueprint $table) {
            $table->dropColumn(['quantidade_adultos', 'quantidade_criancas', 'quantidade_isentos']);
        });
    }
};
