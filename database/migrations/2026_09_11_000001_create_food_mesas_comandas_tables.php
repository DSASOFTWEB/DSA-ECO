<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pontos_atendimento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades')->cascadeOnDelete();
            $table->string('tipo', 10); // mesa, comanda
            $table->unsignedInteger('numero');
            $table->string('nome')->nullable();
            $table->unsignedSmallInteger('capacidade')->nullable();
            $table->string('status', 15)->default('livre'); // livre, ocupada, reservada, bloqueada
            $table->unsignedInteger('ordem')->default(0);
            $table->timestamps();

            $table->unique(['empresa_id', 'unidade_id', 'tipo', 'numero'], 'pontos_atendimento_numero_unique');
            $table->index(['empresa_id', 'unidade_id', 'tipo', 'status'], 'pontos_atendimento_mapa_index');
        });

        Schema::create('atendimentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->constrained('unidades')->cascadeOnDelete();
            $table->foreignId('ponto_atendimento_id')->constrained('pontos_atendimento')->restrictOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('aberto_por_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('fechado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('venda_id')->nullable()->unique()->constrained('vendas')->nullOnDelete();
            $table->string('status', 20)->default('aberto'); // aberto, pre_fechado, fechado, cancelado, transferido
            $table->unsignedSmallInteger('quantidade_pessoas')->default(1);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('desconto', 15, 2)->default(0);
            $table->decimal('percentual_servico', 5, 2)->default(0);
            $table->decimal('valor_servico', 15, 2)->default(0);
            $table->decimal('valor_total', 15, 2)->default(0);
            $table->text('observacao')->nullable();
            $table->timestamp('aberto_em');
            $table->timestamp('pre_fechado_em')->nullable();
            $table->timestamp('fechado_em')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'unidade_id', 'status']);
            $table->index(['ponto_atendimento_id', 'status']);
        });

        Schema::create('atendimento_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('atendimento_id')->constrained('atendimentos')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos')->restrictOnDelete();
            $table->foreignId('lancado_por_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('cancelado_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('quantidade');
            $table->decimal('preco_unitario', 15, 2);
            $table->decimal('desconto', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2);
            $table->string('status', 15)->default('ativo'); // ativo, cancelado
            $table->text('observacao')->nullable();
            $table->text('motivo_cancelamento')->nullable();
            $table->timestamp('cancelado_em')->nullable();
            $table->timestamps();

            $table->index(['atendimento_id', 'status']);
        });

        Schema::create('atendimento_transferencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('atendimento_origem_id')->constrained('atendimentos')->restrictOnDelete();
            $table->foreignId('atendimento_destino_id')->nullable()->constrained('atendimentos')->nullOnDelete();
            $table->foreignId('ponto_origem_id')->constrained('pontos_atendimento')->restrictOnDelete();
            $table->foreignId('ponto_destino_id')->constrained('pontos_atendimento')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->string('tipo', 15); // integral, uniao
            $table->json('detalhes')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'created_at']);
        });

        DB::statement("ALTER TABLE pontos_atendimento ADD CONSTRAINT pontos_atendimento_tipo_check CHECK (tipo IN ('mesa','comanda'))");
        DB::statement("ALTER TABLE pontos_atendimento ADD CONSTRAINT pontos_atendimento_status_check CHECK (status IN ('livre','ocupada','reservada','bloqueada'))");
        DB::statement("ALTER TABLE atendimentos ADD CONSTRAINT atendimentos_status_check CHECK (status IN ('aberto','pre_fechado','fechado','cancelado','transferido'))");
        DB::statement('ALTER TABLE atendimentos ADD CONSTRAINT atendimentos_pessoas_check CHECK (quantidade_pessoas > 0)');
        DB::statement('ALTER TABLE atendimento_itens ADD CONSTRAINT atendimento_itens_quantidade_check CHECK (quantidade > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('atendimento_transferencias');
        Schema::dropIfExists('atendimento_itens');
        Schema::dropIfExists('atendimentos');
        Schema::dropIfExists('pontos_atendimento');
    }
};
