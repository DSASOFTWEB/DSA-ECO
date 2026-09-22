<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotente: em ambientes onde primeiro_vencimento/valor_caucao já
 * existiam (aplicados fora do migrate), só completa o que faltar.
 *
 * MySQL exige índice em contrato_id para a FK; o unique antigo
 * (contrato+competência) cumpria esse papel — por isso criamos um
 * índice simples antes de trocar o unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('contratos', 'primeiro_vencimento')) {
            Schema::table('contratos', function (Blueprint $table) {
                $table->date('primeiro_vencimento')->nullable()->after('dia_vencimento');
            });
        }

        if (! Schema::hasColumn('contratos', 'valor_caucao')) {
            Schema::table('contratos', function (Blueprint $table) {
                $table->decimal('valor_caucao', 15, 2)->default(0)->after('valor_mensal');
            });
        }

        if (! Schema::hasColumn('mensalidades', 'tipo')) {
            Schema::table('mensalidades', function (Blueprint $table) {
                $table->string('tipo', 20)->default('mensalidade')->after('empresa_id');
            });
        }

        // Índice simples para manter a FK após dropar o unique antigo.
        if (! $this->indexExists('mensalidades', 'mensalidades_contrato_id_index')) {
            Schema::table('mensalidades', function (Blueprint $table) {
                $table->index('contrato_id', 'mensalidades_contrato_id_index');
            });
        }

        $this->dropIndexIfExists('mensalidades', 'mensalidades_contrato_id_competencia_unique');

        if (! $this->indexExists('mensalidades', 'mensalidades_contrato_id_competencia_tipo_unique')) {
            Schema::table('mensalidades', function (Blueprint $table) {
                $table->unique(
                    ['contrato_id', 'competencia', 'tipo'],
                    'mensalidades_contrato_id_competencia_tipo_unique'
                );
            });
        }

        if (! $this->indexExists('mensalidades', 'mensalidades_empresa_id_tipo_status_index')) {
            Schema::table('mensalidades', function (Blueprint $table) {
                $table->index(
                    ['empresa_id', 'tipo', 'status'],
                    'mensalidades_empresa_id_tipo_status_index'
                );
            });
        }
    }

    public function down(): void
    {
        $this->dropIndexIfExists('mensalidades', 'mensalidades_empresa_id_tipo_status_index');
        $this->dropIndexIfExists('mensalidades', 'mensalidades_contrato_id_competencia_tipo_unique');

        if (! $this->indexExists('mensalidades', 'mensalidades_contrato_id_competencia_unique')) {
            Schema::table('mensalidades', function (Blueprint $table) {
                $table->unique(
                    ['contrato_id', 'competencia'],
                    'mensalidades_contrato_id_competencia_unique'
                );
            });
        }

        $this->dropIndexIfExists('mensalidades', 'mensalidades_contrato_id_index');

        if (Schema::hasColumn('mensalidades', 'tipo')) {
            Schema::table('mensalidades', function (Blueprint $table) {
                $table->dropColumn('tipo');
            });
        }

        if (Schema::hasColumn('contratos', 'primeiro_vencimento')) {
            Schema::table('contratos', function (Blueprint $table) {
                $table->dropColumn('primeiro_vencimento');
            });
        }

        if (Schema::hasColumn('contratos', 'valor_caucao')) {
            Schema::table('contratos', function (Blueprint $table) {
                $table->dropColumn('valor_caucao');
            });
        }
    }

    protected function indexExists(string $table, string $index): bool
    {
        foreach (Schema::getIndexes($table) as $info) {
            if (($info['name'] ?? '') === $index) {
                return true;
            }
        }

        return false;
    }

    protected function dropIndexIfExists(string $table, string $index): void
    {
        if (! $this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($index) {
            $blueprint->dropIndex($index);
        });
    }
};
