<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\Caixa;
use App\Models\CobrancaPix;
use App\Models\Empresa;
use App\Models\User;
use App\Services\Integrations\Pix\GatewayPix;
use App\Services\Integrations\Pix\GatewayPixResolver;
use Illuminate\Support\Facades\DB;

/**
 * Recebimento Pix do PDV pelo gateway da empresa: gera a cobrança com o
 * total calculado no servidor, acompanha o status e só cria a venda quando
 * o banco confirma o pagamento (idempotente — pode ser chamado a cada poll).
 */
class PdvPixService
{
    public function __construct(
        protected GatewayPixResolver $resolver,
        protected VendaService $vendaService,
    ) {}

    public function iniciar(array $dadosVenda, User $operador, Caixa $caixa): CobrancaPix
    {
        if (! $caixa->estaAberto()) {
            throw new NegocioException('Não é possível receber sem um caixa aberto.');
        }

        $gateway = $this->gatewayDa($operador->empresa_id);
        $valor = $this->vendaService->calcularTotal($dadosVenda['itens'] ?? []);

        if ($valor < 0.01) {
            throw new NegocioException('O total da venda precisa ser maior que zero para gerar o Pix.');
        }

        $cobranca = $gateway->criarCobranca($valor, "Venda PDV - caixa #{$caixa->id}");

        return CobrancaPix::create([
            'empresa_id' => $operador->empresa_id,
            'caixa_id' => $caixa->id,
            'usuario_id' => $operador->id,
            'gateway' => $gateway->nome(),
            'txid' => $cobranca['txid'],
            'valor' => $valor,
            'status' => CobrancaPix::STATUS_PENDENTE,
            'pix_copia_e_cola' => $cobranca['copia_e_cola'],
            'dados_venda' => array_merge($dadosVenda, ['forma_pagamento' => 'pix']),
            'payload' => $cobranca['payload'],
            'expira_em' => now()->addSeconds($cobranca['expiracao_segundos']),
        ]);
    }

    /**
     * Consulta o gateway enquanto estiver pendente e, quando pago, registra
     * a venda. Se o registro falhar (ex.: caixa fechado no meio do caminho),
     * guarda o motivo em `erro` e tenta de novo na próxima chamada.
     */
    public function atualizar(CobrancaPix $cobranca): CobrancaPix
    {
        if ($cobranca->estaPendente()) {
            $consulta = $this->gatewayDaCobranca($cobranca)->consultarCobranca($cobranca->txid);

            if ($consulta['status'] === GatewayPix::STATUS_PAGA) {
                $cobranca->update([
                    'status' => CobrancaPix::STATUS_PAGA,
                    'e2eid' => $consulta['e2eid'],
                    'payload' => $consulta['payload'],
                    'pago_em' => now(),
                ]);
            } elseif ($consulta['status'] === GatewayPix::STATUS_CANCELADA) {
                $cobranca->update(['status' => CobrancaPix::STATUS_CANCELADA]);
            } elseif ($cobranca->expira_em && $cobranca->expira_em->copy()->addMinute()->isPast()) {
                $cobranca->update(['status' => CobrancaPix::STATUS_EXPIRADA]);
            }
        }

        if ($cobranca->estaPaga() && ! $cobranca->venda_id) {
            $this->registrarVenda($cobranca);
        }

        return $cobranca->fresh();
    }

    public function cancelar(CobrancaPix $cobranca): CobrancaPix
    {
        $cobranca = $this->atualizar($cobranca);

        if (! $cobranca->estaPendente()) {
            return $cobranca;
        }

        $this->gatewayDaCobranca($cobranca)->cancelarCobranca($cobranca->txid);
        $cobranca->update(['status' => CobrancaPix::STATUS_CANCELADA]);

        return $cobranca;
    }

    protected function registrarVenda(CobrancaPix $cobranca): void
    {
        DB::transaction(function () use ($cobranca): void {
            $travada = CobrancaPix::whereKey($cobranca->id)->lockForUpdate()->first();

            if (! $travada || $travada->venda_id) {
                return;
            }

            if (! $travada->caixa || ! $travada->usuario) {
                $travada->update(['erro' => 'O caixa ou o operador desta cobrança não existe mais. Registre a venda manualmente.']);

                return;
            }

            try {
                $venda = $this->vendaService->criar(
                    $travada->dados_venda,
                    $travada->usuario,
                    $travada->caixa,
                    [
                        'gateway' => $travada->gateway,
                        'gateway_payment_id' => $travada->e2eid ?: $travada->txid,
                        'payload' => ['txid' => $travada->txid, 'e2eid' => $travada->e2eid],
                    ],
                );
            } catch (NegocioException $e) {
                $travada->update(['erro' => $e->getMessage()]);

                return;
            }

            $travada->update(['venda_id' => $venda->id, 'erro' => null]);
        });
    }

    protected function gatewayDaCobranca(CobrancaPix $cobranca): GatewayPix
    {
        $gateway = $this->resolver->porNome(Empresa::findOrFail($cobranca->empresa_id), $cobranca->gateway);

        if (! $gateway) {
            throw new NegocioException("Gateway Pix \"{$cobranca->gateway}\" indisponível para esta cobrança.");
        }

        return $gateway;
    }

    protected function gatewayDa(int $empresaId): GatewayPix
    {
        $gateway = $this->resolver->paraEmpresa(Empresa::findOrFail($empresaId));

        if (! $gateway) {
            throw new NegocioException('O gateway Pix da empresa não está configurado.');
        }

        return $gateway;
    }
}
