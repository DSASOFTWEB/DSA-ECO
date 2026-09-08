<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carteirinhas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('dependente_id')->nullable()->constrained('dependentes')->cascadeOnDelete();
            $table->string('codigo', 64)->unique(); // token embutido no QR Code
            $table->string('status', 20)->default('ativa'); // ativa, bloqueada, expirada, cancelada
            $table->timestamp('emitida_em');
            $table->date('expira_em')->nullable();
            $table->string('motivo_bloqueio')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('cliente_id');
            $table->index('dependente_id');
            $table->index('status');
        });

        // Carteirinha pertence exatamente a um titular: cliente OU dependente, nunca os dois nem nenhum
        DB::statement('ALTER TABLE carteirinhas ADD CONSTRAINT carteirinhas_titular_check CHECK (
            (cliente_id IS NOT NULL AND dependente_id IS NULL) OR
            (cliente_id IS NULL AND dependente_id IS NOT NULL)
        )');

        Schema::create('acessos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carteirinha_id')->constrained('carteirinhas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades')->cascadeOnDelete();
            $table->string('tipo', 10); // entrada, saida
            $table->string('dispositivo')->nullable(); // identificador da catraca/totem/leitor
            $table->boolean('autorizado')->default(true);
            $table->string('motivo_negado')->nullable();
            $table->timestamp('registrado_em')->useCurrent();
            $table->timestamps();

            $table->index(['carteirinha_id', 'registrado_em']);
            $table->index(['unidade_id', 'registrado_em']);
        });

        DB::statement("ALTER TABLE acessos ADD CONSTRAINT acessos_tipo_check CHECK (tipo IN ('entrada','saida'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('acessos');
        Schema::dropIfExists('carteirinhas');
    }
};
