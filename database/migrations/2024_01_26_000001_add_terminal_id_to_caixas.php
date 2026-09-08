<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caixas', function (Blueprint $table) {
            $table->foreignId('terminal_id')->nullable()->after('unidade_id')
                ->constrained('terminais')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('caixas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('terminal_id');
        });
    }
};
