<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->foreignId('marca_id')->nullable()->after('categoria_id')
                ->constrained('marcas')->nullOnDelete();
            $table->foreignId('fornecedor_id')->nullable()->after('marca_id')
                ->constrained('fornecedores')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('marca_id');
            $table->dropConstrainedForeignId('fornecedor_id');
        });
    }
};
