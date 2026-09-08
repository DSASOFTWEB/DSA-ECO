<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acessos', function (Blueprint $table) {
            $table->foreignId('dependente_id')->nullable()->after('cliente_id')->constrained('dependentes')->nullOnDelete();
            $table->index('dependente_id');
        });
    }

    public function down(): void
    {
        Schema::table('acessos', function (Blueprint $table) {
            $table->dropForeign(['dependente_id']);
            $table->dropColumn('dependente_id');
        });
    }
};
