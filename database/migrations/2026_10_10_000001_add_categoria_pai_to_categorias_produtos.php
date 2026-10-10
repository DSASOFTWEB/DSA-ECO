<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Subcategoria reaproveita a própria tabela de categoria: uma
     * categoria com `categoria_pai_id` preenchido é, na prática, uma
     * subcategoria daquela outra. Evita duplicar cadastro/tela/model só
     * pra um segundo nível de hierarquia.
     */
    public function up(): void
    {
        Schema::table('categorias_produtos', function (Blueprint $table) {
            $table->foreignId('categoria_pai_id')->nullable()->after('empresa_id')
                ->constrained('categorias_produtos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('categorias_produtos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('categoria_pai_id');
        });
    }
};
