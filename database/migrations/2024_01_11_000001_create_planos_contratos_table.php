<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->decimal('valor', 15, 2);
            $table->string('periodicidade', 20)->default('mensal'); // mensal, trimestral, semestral, anual
            $table->unsignedSmallInteger('max_dependentes')->default(0);
            $table->unsignedTinyInteger('dias_acesso_semana')->default(7);
            $table->boolean('permite_congelamento')->default(false);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('empresa_id');
            $table->index('ativo');
        });

        Schema::create('contratos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('plano_id')->constrained('planos')->restrictOnDelete();
            $table->foreignId('vendedor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('numero_contrato', 30)->nullable()->unique();
            $table->date('data_inicio');
            $table->date('data_fim')->nullable();
            $table->unsignedTinyInteger('dia_vencimento'); // 1 a 31 (meses curtos: último dia)
            $table->decimal('valor_mensal', 15, 2);
            $table->decimal('desconto_percentual', 5, 2)->default(0);
            $table->string('status', 20)->default('ativo'); // ativo, suspenso, cancelado, encerrado
            $table->text('motivo_cancelamento')->nullable();
            $table->timestamp('cancelado_em')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('empresa_id');
            $table->index('unidade_id');
            $table->index('cliente_id');
            $table->index('vendedor_id');
            $table->index('status');
        });

        Schema::create('contrato_dependente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $table->foreignId('dependente_id')->constrained('dependentes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['contrato_id', 'dependente_id']);
        });

        // Regra: dia_vencimento entre 1 e 31 (meses curtos usam o último dia disponível).
        DB::statement('ALTER TABLE contratos ADD CONSTRAINT contratos_dia_vencimento_check CHECK (dia_vencimento BETWEEN 1 AND 31)');
    }

    public function down(): void
    {
        Schema::dropIfExists('contrato_dependente');
        Schema::dropIfExists('contratos');
        Schema::dropIfExists('planos');
    }
};
