<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Forma de pagamento cadastrada pela empresa e oferecida no PDV e no
 * fechamento de mesa/comanda. O `tipo` é o que vai gravado na venda e no
 * caixa (mesmas chaves de App\Support\Financeiro::FORMAS_PAGAMENTO), então
 * relatórios continuam agrupando igual. `integrado` só vale para Pix:
 * gera o QR Code no gateway da empresa e espera o banco confirmar.
 */
class FormaPagamento extends Model
{
    use BelongsToTenant;

    public const TIPOS = [
        'dinheiro' => 'Dinheiro',
        'pix' => 'Pix',
        'cartao_credito' => 'Cartão de crédito',
        'cartao_debito' => 'Cartão de débito',
        'transferencia' => 'Transferência',
        'outro' => 'Outro',
    ];

    protected $table = 'formas_pagamento';

    protected $fillable = ['empresa_id', 'nome', 'tipo', 'integrado', 'ativo', 'ordem'];

    protected $casts = [
        'integrado' => 'boolean',
        'ativo' => 'boolean',
        'ordem' => 'integer',
    ];

    public function ehPixIntegrado(): bool
    {
        return $this->tipo === 'pix' && $this->integrado;
    }

    public function getTipoLabelAttribute(): string
    {
        return self::TIPOS[$this->tipo] ?? $this->tipo;
    }

    /**
     * Formas ativas da empresa, na ordem do cadastro. Na primeira vez cria
     * as formas padrão, para nenhuma tela de pagamento ficar vazia.
     *
     * @return Collection<int, FormaPagamento>
     */
    public static function ativasDaEmpresa(int $empresaId): Collection
    {
        self::garantirPadroes($empresaId);

        return self::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->orderBy('ordem')
            ->orderBy('id')
            ->get();
    }

    public static function garantirPadroes(int $empresaId): void
    {
        if (self::withoutGlobalScopes()->where('empresa_id', $empresaId)->exists()) {
            return;
        }

        $padroes = [
            ['nome' => 'Dinheiro', 'tipo' => 'dinheiro', 'integrado' => false],
            ['nome' => 'Pix', 'tipo' => 'pix', 'integrado' => false],
        ];

        if (Empresa::find($empresaId)?->gatewayPixAtivo()) {
            $padroes[] = ['nome' => 'Pix QR Code', 'tipo' => 'pix', 'integrado' => true];
        }

        $padroes[] = ['nome' => 'Cartão de crédito', 'tipo' => 'cartao_credito', 'integrado' => false];
        $padroes[] = ['nome' => 'Cartão de débito', 'tipo' => 'cartao_debito', 'integrado' => false];

        foreach ($padroes as $ordem => $forma) {
            self::withoutGlobalScopes()->create($forma + [
                'empresa_id' => $empresaId,
                'ativo' => true,
                'ordem' => $ordem + 1,
            ]);
        }
    }

    /**
     * Dados que as telas de pagamento (Alpine) usam para montar os botões.
     */
    public function paraTela(): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'tipo' => $this->tipo,
            'integrado' => $this->ehPixIntegrado(),
        ];
    }
}
