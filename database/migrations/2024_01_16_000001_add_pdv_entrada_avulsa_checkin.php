<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_entrada', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nome');
            $table->decimal('valor', 15, 2);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('empresa_id');
            $table->index('ativo');
        });

        // venda_itens passa a representar um produto OU um tipo de entrada avulsa
        // (ingresso vendido no PDV) — nunca os dois, nunca nenhum.
        DB::statement('ALTER TABLE venda_itens DROP FOREIGN KEY venda_itens_produto_id_foreign');
        DB::statement('ALTER TABLE venda_itens MODIFY produto_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE venda_itens ADD CONSTRAINT venda_itens_produto_id_foreign FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE RESTRICT');

        Schema::table('venda_itens', function (Blueprint $table) {
            $table->foreignId('tipo_entrada_id')->nullable()->after('produto_id')->constrained('tipos_entrada')->restrictOnDelete();
            $table->index('tipo_entrada_id');
        });

        DB::statement('ALTER TABLE venda_itens ADD CONSTRAINT venda_itens_item_check CHECK (
            (produto_id IS NOT NULL AND tipo_entrada_id IS NULL) OR
            (produto_id IS NULL AND tipo_entrada_id IS NOT NULL)
        )');

        Schema::table('vendas', function (Blueprint $table) {
            $table->text('observacao')->nullable()->after('forma_pagamento');
        });

        // acessos passa a ser gerado também manualmente pelo PDV (entrada avulsa paga
        // ou check-in de cliente com plano), não só pela leitura de QR na catraca —
        // por isso carteirinha_id deixa de ser obrigatório.
        DB::statement('ALTER TABLE acessos DROP FOREIGN KEY acessos_carteirinha_id_foreign');
        DB::statement('ALTER TABLE acessos MODIFY carteirinha_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE acessos ADD CONSTRAINT acessos_carteirinha_id_foreign FOREIGN KEY (carteirinha_id) REFERENCES carteirinhas(id) ON DELETE CASCADE');

        Schema::table('acessos', function (Blueprint $table) {
            $table->foreignId('cliente_id')->nullable()->after('carteirinha_id')->constrained('clientes')->nullOnDelete();
            $table->foreignId('venda_id')->nullable()->after('cliente_id')->constrained('vendas')->nullOnDelete();
            $table->string('origem', 20)->default('catraca')->after('tipo'); // catraca, pdv_avulsa, pdv_plano
            $table->foreignId('registrado_por_id')->nullable()->after('origem')->constrained('users')->nullOnDelete();

            $table->index('cliente_id');
        });

        DB::statement("ALTER TABLE acessos ADD CONSTRAINT acessos_origem_check CHECK (origem IN ('catraca','pdv_avulsa','pdv_plano'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE acessos DROP CHECK acessos_origem_check');
        Schema::table('acessos', function (Blueprint $table) {
            $table->dropForeign(['registrado_por_id']);
            $table->dropForeign(['venda_id']);
            $table->dropForeign(['cliente_id']);
            $table->dropColumn(['registrado_por_id', 'origem', 'venda_id', 'cliente_id']);
        });
        DB::statement('ALTER TABLE acessos DROP FOREIGN KEY acessos_carteirinha_id_foreign');
        DB::statement('ALTER TABLE acessos MODIFY carteirinha_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE acessos ADD CONSTRAINT acessos_carteirinha_id_foreign FOREIGN KEY (carteirinha_id) REFERENCES carteirinhas(id) ON DELETE CASCADE');

        Schema::table('vendas', function (Blueprint $table) {
            $table->dropColumn('observacao');
        });

        DB::statement('ALTER TABLE venda_itens DROP CHECK venda_itens_item_check');
        Schema::table('venda_itens', function (Blueprint $table) {
            $table->dropForeign(['tipo_entrada_id']);
            $table->dropColumn('tipo_entrada_id');
        });
        DB::statement('ALTER TABLE venda_itens DROP FOREIGN KEY venda_itens_produto_id_foreign');
        DB::statement('ALTER TABLE venda_itens MODIFY produto_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE venda_itens ADD CONSTRAINT venda_itens_produto_id_foreign FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE RESTRICT');

        Schema::dropIfExists('tipos_entrada');
    }
};
