<?php

namespace App\Services;

use App\Models\Comissao;
use App\Models\Contrato;
use App\Models\User;
use App\Models\Venda;
use Illuminate\Support\Facades\DB;

class ComissaoService
{
    /**
     * Sem percentual padrão de propósito: só gera comissão pra vendedor com
     * User::percentual_comissao preenchido (tela de usuários). Em branco =
     * não comissiona essa pessoa, em nenhuma venda nem contrato.
     */
    public function calcularParaContrato(Contrato $contrato): ?Comissao
    {
        if (! $contrato->vendedor_id) {
            return null;
        }

        $percentual = $contrato->vendedor->percentual_comissao;

        if ($percentual === null) {
            return null;
        }

        return DB::transaction(fn () => Comissao::create([
            'empresa_id' => $contrato->empresa_id,
            'vendedor_id' => $contrato->vendedor_id,
            'contrato_id' => $contrato->id,
            'tipo' => 'contrato',
            'base_calculo' => $contrato->valor_mensal,
            'percentual' => $percentual,
            'valor' => round($contrato->valor_mensal * ((float) $percentual / 100), 2),
            'status' => 'pendente',
        ]));
    }

    public function calcularParaVenda(Venda $venda): ?Comissao
    {
        if (! $venda->vendedor_id) {
            return null;
        }

        $percentual = $venda->vendedor->percentual_comissao;

        if ($percentual === null) {
            return null;
        }

        return DB::transaction(fn () => Comissao::create([
            'empresa_id' => $venda->empresa_id,
            'vendedor_id' => $venda->vendedor_id,
            'venda_id' => $venda->id,
            'tipo' => 'venda',
            'base_calculo' => $venda->valor_total,
            'percentual' => $percentual,
            'valor' => round($venda->valor_total * ((float) $percentual / 100), 2),
            'status' => 'pendente',
        ]));
    }

    /**
     * Comissão de REATIVAÇÃO — contrato que estava cancelado e voltou a
     * ficar ativo (ver ContratoService::reativar). Usa o percentual próprio
     * de reativação do vendedor (normalmente maior que o de venda/contrato
     * novo, já que "trazer o cliente de volta" costuma valer mais); em
     * branco não gera comissão nenhuma, igual aos outros dois tipos.
     */
    public function calcularParaReativacao(Contrato $contrato): ?Comissao
    {
        if (! $contrato->vendedor_id) {
            return null;
        }

        $percentual = $contrato->vendedor->percentual_comissao_reativacao;

        if ($percentual === null) {
            return null;
        }

        return DB::transaction(fn () => Comissao::create([
            'empresa_id' => $contrato->empresa_id,
            'vendedor_id' => $contrato->vendedor_id,
            'contrato_id' => $contrato->id,
            'tipo' => 'reativacao',
            'base_calculo' => $contrato->valor_mensal,
            'percentual' => $percentual,
            'valor' => round($contrato->valor_mensal * ((float) $percentual / 100), 2),
            'status' => 'pendente',
        ]));
    }

    public function marcarComoPaga(Comissao $comissao): Comissao
    {
        $comissao->update(['status' => 'pago', 'pago_em' => now()->toDateString()]);

        return $comissao;
    }

    public function totalPendentePorVendedor(User $vendedor): float
    {
        return (float) $vendedor->comissoes()->where('status', 'pendente')->sum('valor');
    }
}
