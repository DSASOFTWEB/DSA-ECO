<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * users: tabela de usuários do sistema (equipe do parque), já preparada
     * para multi-tenant (empresa_id) e multi-unidade (unidade_id).
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->foreignId('unidade_id')->nullable()->constrained('unidades')->nullOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('telefone', 20)->nullable();
            $table->string('cpf', 14)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('status', 20)->default('ativo'); // ativo, inativo
            $table->timestamp('ultimo_login_at')->nullable();
            $table->string('ultimo_login_ip', 45)->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('empresa_id');
            $table->index('unidade_id');
            $table->index('status');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
