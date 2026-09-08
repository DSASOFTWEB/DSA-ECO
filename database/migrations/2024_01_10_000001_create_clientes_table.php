<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidade_id')->nullable()->constrained('unidades')->nullOnDelete();
            $table->string('nome');
            $table->string('cpf', 14);
            $table->string('rg', 20)->nullable();
            $table->date('data_nascimento')->nullable();
            $table->string('email')->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->string('cep', 9)->nullable();
            $table->string('endereco')->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('complemento')->nullable();
            $table->string('bairro')->nullable();
            $table->string('cidade')->nullable();
            $table->string('uf', 2)->nullable();
            $table->string('foto_path')->nullable();
            $table->string('status', 20)->default('ativo'); // ativo, inativo, bloqueado
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'cpf']);
            $table->index('unidade_id');
            $table->index('status');
            $table->index('nome');
        });

        Schema::create('dependentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('nome');
            $table->string('cpf', 14)->nullable();
            $table->date('data_nascimento');
            $table->string('parentesco', 40)->nullable(); // filho(a), conjuge, outro
            $table->string('foto_path')->nullable();
            $table->string('status', 20)->default('ativo'); // ativo, inativo
            $table->timestamps();
            $table->softDeletes();

            $table->index('cliente_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dependentes');
        Schema::dropIfExists('clientes');
    }
};
