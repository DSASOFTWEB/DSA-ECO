<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Venda online (link público de autoatendimento) não tem um operador
        // humano vendendo — o pagamento é confirmado sozinho pelo webhook do
        // Mercado Pago, então vendedor_id deixa de ser obrigatório.
        DB::statement('ALTER TABLE vendas DROP FOREIGN KEY vendas_vendedor_id_foreign');
        DB::statement('ALTER TABLE vendas MODIFY vendedor_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE vendas ADD CONSTRAINT vendas_vendedor_id_foreign FOREIGN KEY (vendedor_id) REFERENCES users(id) ON DELETE RESTRICT');

        DB::statement('ALTER TABLE acessos DROP CHECK acessos_origem_check');
        DB::statement("ALTER TABLE acessos ADD CONSTRAINT acessos_origem_check CHECK (origem IN ('catraca','pdv_avulsa','pdv_plano','online'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE acessos DROP CHECK acessos_origem_check');
        DB::statement("ALTER TABLE acessos ADD CONSTRAINT acessos_origem_check CHECK (origem IN ('catraca','pdv_avulsa','pdv_plano'))");

        DB::statement('ALTER TABLE vendas DROP FOREIGN KEY vendas_vendedor_id_foreign');
        DB::statement('ALTER TABLE vendas MODIFY vendedor_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE vendas ADD CONSTRAINT vendas_vendedor_id_foreign FOREIGN KEY (vendedor_id) REFERENCES users(id) ON DELETE RESTRICT');
    }
};
