<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quartos', function (Blueprint $table) {
            $table->boolean('precisa_limpeza')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('quartos', function (Blueprint $table) {
            $table->dropColumn('precisa_limpeza');
        });
    }
};
