<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acessos', function (Blueprint $table) {
            $table->foreignId('plano_id')->nullable()->after('tipo_entrada_id')
                ->constrained('planos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('acessos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plano_id');
        });
    }
};
