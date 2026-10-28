<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\Atendimento;
use App\Models\Caixa;
use App\Models\CobrancaPix;
use App\Models\Empresa;
use App\Models\Hospedagem;
use App\Models\User;
use App\Models\Venda;
use App\Services\Fiscal\HospedagemFiscalService;
use App\Services\Fiscal\VendaFiscalService;
use App\Services\Food\AtendimentoService;
use App\Services\Integrations\Pix\GatewayPix;
use App\Services\Integrations\Pix\GatewayPixResolver;
use Illuminate\Support\Facades\DB;

/**
 * Recebimento Pix integrado pelo gateway da empresa (PDV, fechamento de
 * mesa/comanda e check-out da hospedagem): gera a cobrança com o total
 * calculado no servidor, acompanha o status e só registra a venda / fecha
 * a conta quando o banco confirma o pagamento (idempotente — pode ser
 * chamado a cada poll).
 */
class PdvPixService
{
    public function __construct(
        protected GatewayPixResolver $resolver,
        protected VendaService $vendaService,
        protected AtendimentoService $atendimentoService,
        protected HospedagemService $hospedagemService,
        protected HospedagemFiscalService $hospedagemFiscalService,
        protected VendaFiscalService $vendaFiscalService,
    ) {}

    public function iniciar(array $dadosVenda, User $operador, Caixa $caixa): CobrancaPix
    {
        if (! $caixa->estaAberto()) {
            throw new NegocioException('Não é possível receber sem um caixa aberto.');
        }

        $valor = $this->vendaService->calcularTotal($dadosVenda['itens'] ?? []);

        if ($valor < 0.01) {
            throw new NegocioException('O total da venda precisa ser maior que zero para gerar o Pix.');
        }

        // Pagamento dividido: o QR cobra só a parcela do Pix integrado; as
        // demais (dinheiro, cartão...) já foram recebidas no balcão.
        if (! empty($dadosVenda['pagamentos'])) {
            $parcelas = $this->vendaService->resolverPagamentos($dadosVenda, $valor)['parcelas'];
            $integrada = collect($parcelas)->firstWhere('integrado', true);

            if (! $integrada) {
                throw new NegocioException('Nenhuma parcela usa o Pix com QR Code.');
            }

            return $this->criarCobranca($operador, $caixa, (float) $integrada['valor'], "Venda PDV - caixa #{$caixa->id}", [
                'dados_venda' => $dadosVenda,
            ]);
        }

        return $this->criarCobranca($operador, $caixa, $valor, "Venda PDV - caixa #{$caixa->id}", [
            'dados_venda' => array_merge($dadosVenda, ['forma_pagamento' => 'pix']),
        ]);
    }

    /**
     * Pix integrado no fechamento de mesa/comanda: só fecha a conta quando o
     * banco confirmar.
     */
    public function iniciarAtendimento(Atendimento $atendimento, array $dadosFechamento, User $operador, Caixa $caixa): CobrancaPix
    {
        if (! $caixa->estaAberto() || $caixa->unidade_id !== $atendimento->unidade_id) {
            throw new NegocioException('Selecione um caixa aberto da mesma unidade.');
        }

        if (! $atendimento->estaEmAndamento()) {
            throw new NegocioException('Este atendimento não está disponível para fechamento.');
        }

        $valor = $this->atendimentoService->calcularTotalFechamento($atendimento, $dadosFechamento);

        if ($valor < 0.01) {
            throw new NegocioException('O total da conta precisa ser maior que zero para gerar o Pix.');
        }

        $pendente = $this->reaproveitarPendente(CobrancaPix::where('atendimento_id', $atendimento->id), $valor, $caixa);
        if ($pendente) {
            return $pendente;
        }

        return $this->criarCobranca($operador, $caixa, $valor, "Conta {$atendimento->ponto->identificacao} - atendimento #{$atendimento->id}", [
            'atendimento_id' => $atendimento->id,
            'dados_venda' => array_merge($dadosFechamento, [
                'caixa_id' => $caixa->id,
                'pagamentos' => [['forma' => 'pix', 'valor' => $valor]],
            ]),
        ]);
    }

    /**
     * Pix integrado no check-out da hospedagem: só finaliza a estadia (e
     * emite as notas marcadas) quando o banco confirmar.
     */
    public function iniciarHospedagem(Hospedagem $hospedagem, array $dadosCheckout, User $operador, Caixa $caixa): CobrancaPix
    {
        if (! $caixa->estaAberto()) {
            throw new NegocioException('Abra o caixa antes de fazer o check-out.');
        }

        if (! $hospedagem->estaHospedado()) {
            throw new NegocioException('Só é possível fazer check-out de quem já fez check-in.');
        }

        $valor = $this->hospedagemService->calcularTotal($hospedagem, (float) ($dadosCheckout['desconto'] ?? 0));

        if ($valor < 0.01) {
            throw new NegocioException('O total da conta precisa ser maior que zero para gerar o Pix.');
        }

        $pendente = $this->reaproveitarPendente(CobrancaPix::where('hospedagem_id', $hospedagem->id), $valor, $caixa);
        if ($pendente) {
            return $pendente;
        }

        return $this->criarCobranca($operador, $caixa, $valor, "Hospedagem #{$hospedagem->id} - quarto {$hospedagem->quarto->numero}", [
            'hospedagem_id' => $hospedagem->id,
            'dados_venda' => [
                'desconto' => (float) ($dadosCheckout['desconto'] ?? 0),
                'emitir_nfce' => (bool) ($dadosCheckout['emitir_nfce'] ?? false),
                'emitir_nfse' => (bool) ($dadosCheckout['emitir_nfse'] ?? false),
            ],
        ]);
    }

    /**
     * Consulta o gateway enquanto estiver pendente e, quando pago, registra
     * a venda / fecha a conta. Se o registro falhar (ex.: caixa fechado no
     * meio do caminho), guarda o motivo em `erro` e tenta de novo na próxima
     * chamada.
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

        if ($cobranca->estaPaga() && ! $this->concluida($cobranca)) {
            // Notas fiscais saem fora da transação (chamada à SEFAZ).
            match ($this->registrarVenda($cobranca)) {
                'hospedagem' => $this->emitirNotasHospedagem($cobranca->fresh()),
                'venda' => $this->emitirNfceVenda($cobranca->fresh()),
                default => null,
            };
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

    /**
     * Venda registrada (PDV, mesa) ou estadia finalizada (hospedagem).
     */
    public function concluida(CobrancaPix $cobranca): bool
    {
        if ($cobranca->hospedagem_id) {
            return Hospedagem::withoutGlobalScopes()->whereKey($cobranca->hospedagem_id)->value('status') === 'finalizado';
        }

        return (bool) $cobranca->venda_id;
    }

    /**
     * Reabrir a tela com o mesmo valor reaproveita o QR pendente; com valor
     * diferente (item lançado, desconto mudou) o antigo é cancelado antes.
     */
    protected function reaproveitarPendente($query, float $valor, Caixa $caixa): ?CobrancaPix
    {
        $pendente = $query->where('status', CobrancaPix::STATUS_PENDENTE)->latest('id')->first();

        if (! $pendente) {
            return null;
        }

        $pendente = $this->atualizar($pendente);

        if ($pendente->estaPaga()) {
            return $pendente;
        }

        if ($pendente->estaPendente()) {
            if (abs((float) $pendente->valor - $valor) < 0.009 && (int) $pendente->caixa_id === $caixa->id) {
                return $pendente;
            }

            $this->cancelar($pendente);
        }

        return null;
    }

    protected function criarCobranca(User $operador, Caixa $caixa, float $valor, string $descricao, array $atributos): CobrancaPix
    {
        $gateway = $this->gatewayDa($operador->empresa_id);
        $cobranca = $gateway->criarCobranca($valor, $descricao);

        return CobrancaPix::create(array_merge([
            'empresa_id' => $operador->empresa_id,
            'caixa_id' => $caixa->id,
            'usuario_id' => $operador->id,
            'gateway' => $gateway->nome(),
            'txid' => $cobranca['txid'],
            'valor' => $valor,
            'status' => CobrancaPix::STATUS_PENDENTE,
            'pix_copia_e_cola' => $cobranca['copia_e_cola'],
            'payload' => $cobranca['payload'],
            'expira_em' => now()->addSeconds($cobranca['expiracao_segundos']),
        ], $atributos));
    }

    /**
     * @return string|null o que foi concluído nesta chamada: "hospedagem" ou
     *                     "venda" (para emitir as notas fora da transação).
     */
    protected function registrarVenda(CobrancaPix $cobranca): ?string
    {
        return DB::transaction(function () use ($cobranca): ?string {
            $travada = CobrancaPix::whereKey($cobranca->id)->lockForUpdate()->first();

            if (! $travada || $this->concluida($travada)) {
                return null;
            }

            if (! $travada->caixa || ! $travada->usuario) {
                $travada->update(['erro' => 'O caixa ou o operador desta cobrança não existe mais. Registre a venda manualmente.']);

                return null;
            }

            $pagamentoGateway = [
                'gateway' => $travada->gateway,
                'gateway_payment_id' => $travada->e2eid ?: $travada->txid,
                'payload' => ['txid' => $travada->txid, 'e2eid' => $travada->e2eid],
            ];

            try {
                if ($travada->hospedagem_id) {
                    $this->hospedagemService->checkout(
                        Hospedagem::withoutGlobalScopes()->findOrFail($travada->hospedagem_id),
                        $travada->caixa,
                        $travada->usuario,
                        'pix',
                        (float) ($travada->dados_venda['desconto'] ?? 0),
                    );
                    $travada->update(['erro' => null]);

                    return 'hospedagem';
                }

                if ($travada->atendimento_id) {
                    $vendaId = $this->atendimentoService
                        ->fechar(Atendimento::withoutGlobalScopes()->findOrFail($travada->atendimento_id), $travada->dados_venda, $travada->usuario, $pagamentoGateway)
                        ->venda_id;
                } else {
                    $vendaId = $this->vendaService->criar(
                        $travada->dados_venda,
                        $travada->usuario,
                        $travada->caixa,
                        $pagamentoGateway,
                    )->id;
                }
            } catch (NegocioException $e) {
                $travada->update(['erro' => $e->getMessage()]);

                return null;
            }

            $travada->update(['venda_id' => $vendaId, 'erro' => null]);

            return 'venda';
        });
    }

    /**
     * Notas marcadas no check-out; o resultado fica na cobrança para a tela
     * mostrar ao operador quando o pagamento confirmar.
     */
    protected function emitirNotasHospedagem(CobrancaPix $cobranca): void
    {
        $dados = $cobranca->dados_venda;

        if (empty($dados['emitir_nfce']) && empty($dados['emitir_nfse'])) {
            return;
        }

        $resultado = $this->hospedagemFiscalService->emitirAposCheckout(
            Hospedagem::withoutGlobalScopes()->findOrFail($cobranca->hospedagem_id),
            $cobranca->usuario,
            ! empty($dados['emitir_nfce']),
            ! empty($dados['emitir_nfse']),
        );

        $cobranca->update(['dados_venda' => array_merge($dados, ['fiscal' => $resultado])]);
    }

    /**
     * "F4 emitir NFC-e" escolhido antes de gerar o QR (PDV ou mesa): a nota
     * sai agora que a venda existe. O resultado fica na cobrança para a tela
     * mostrar quando redirecionar.
     */
    protected function emitirNfceVenda(CobrancaPix $cobranca): void
    {
        $dados = $cobranca->dados_venda;

        if (empty($dados['emitir_nfce']) || ! $cobranca->venda_id || ! $cobranca->usuario) {
            return;
        }

        $resultado = $this->vendaFiscalService->emitirAposVenda(
            Venda::withoutGlobalScopes()->findOrFail($cobranca->venda_id),
            $cobranca->usuario,
        );

        $cobranca->update(['dados_venda' => array_merge($dados, ['fiscal_venda' => $resultado])]);
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
