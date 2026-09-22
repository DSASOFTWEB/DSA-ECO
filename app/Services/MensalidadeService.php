<?php

namespace App\Services;

use App\Models\Caixa;
use App\Models\CaixaMovimentacao;
use App\Models\Contrato;
use App\Models\Mensalidade;
use App\Models\Pagamento;
use App\Repositories\Contracts\ContratoRepositoryInterface;
use App\Repositories\Contracts\MensalidadeRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Núcleo financeiro recorrente: geração das mensalidades a partir dos
 * contratos ativos, baixa de pagamento e controle de atraso/inadimplência.
 * Todas as operações que alteram saldo/situação financeira rodam dentro
 * de DB::transaction, conforme exigido pelas regras do projeto.
 */
class MensalidadeService
{
    public function __construct(
        protected MensalidadeRepositoryInterface $mensalidades,
        protected ContratoRepositoryInterface $contratos,
    ) {}

    /**
     * Gera (se ainda não existir) a mensalidade do mês corrente para todo
     * contrato ativo cujo dia de vencimento seja o informado. Idempotente:
     * pode ser executado mais de uma vez no mesmo dia sem duplicar cobranças
     * (respeita a constraint única contrato_id+competencia).
     */
    public function gerarMensalidadesDoDia(int $dia, ?Carbon $referencia = null): int
    {
        $referencia ??= now();
        $competencia = $referencia->copy()->startOfMonth();
        $geradas = 0;

        foreach ($this->contratos->ativosComVencimentoNoDia($dia, $referencia) as $contrato) {
            try {
                $mensalidade = $this->gerarParaContrato($contrato, $competencia);
                $geradas += $mensalidade->wasRecentlyCreated ? 1 : 0;
            } catch (\Throwable $e) {
                // Uma falha isolada não pode interromper a geração dos demais contratos.
                Log::error('Falha ao gerar mensalidade', [
                    'contrato_id' => $contrato->id,
                    'erro' => $e->getMessage(),
                ]);
            }
        }

        return $geradas;
    }

    public function gerarParaContrato(Contrato $contrato, Carbon $competencia): Mensalidade
    {
        $existente = $this->mensalidades->porContratoECompetencia($contrato->id, $competencia);

        if ($existente) {
            return $existente;
        }

        return DB::transaction(function () use ($contrato, $competencia) {
            $valorOriginal = $contrato->valor_mensal;
            $desconto = round($valorOriginal * ($contrato->desconto_percentual / 100), 2);
            $valorTotal = $valorOriginal - $desconto;

            $dataInicio = Carbon::parse($contrato->data_inicio)->startOfDay();
            $vencimento = $competencia->copy()->day(min((int) $contrato->dia_vencimento, $competencia->daysInMonth));

            // Se o dia de vencimento do mês de início já passou, a 1ª cobrança
            // vence na data de início — evita mensalidade "pendente atrasada"
            // no mesmo dia em que o contrato é criado.
            if ($vencimento->lt($dataInicio)) {
                $vencimento = $dataInicio->copy();
            }

            return $this->mensalidades->create([
                'contrato_id' => $contrato->id,
                'empresa_id' => $contrato->empresa_id,
                'tipo' => 'mensalidade',
                'competencia' => $competencia->toDateString(),
                'valor_original' => $valorOriginal,
                'desconto' => $desconto,
                'acrescimo' => 0,
                'valor_total' => $valorTotal,
                'data_vencimento' => $vencimento->toDateString(),
                'status' => 'pendente',
            ]);
        });
    }

    /**
     * Registra a caução como cobrança de entrada, separada das mensalidades
     * recorrentes. A constraint contrato+competência+tipo mantém a operação
     * idempotente sem impedir que caução e mensalidade caiam no mesmo mês.
     */
    public function gerarCaucaoParaContrato(Contrato $contrato): Mensalidade
    {
        $competencia = $contrato->data_inicio->copy()->startOfMonth();
        $existente = $this->mensalidades->porContratoECompetencia($contrato->id, $competencia, 'caucao');

        if ($existente) {
            return $existente;
        }

        return $this->mensalidades->create([
            'contrato_id' => $contrato->id,
            'empresa_id' => $contrato->empresa_id,
            'tipo' => 'caucao',
            'competencia' => $competencia->toDateString(),
            'valor_original' => $contrato->valor_caucao,
            'desconto' => 0,
            'acrescimo' => 0,
            'valor_total' => $contrato->valor_caucao,
            'data_vencimento' => $contrato->data_inicio->toDateString(),
            'status' => 'pendente',
            'observacoes' => 'Caução de entrada do contrato.',
        ]);
    }

    public function gerarPrimeiraMensalidade(Contrato $contrato): Mensalidade
    {
        $vencimento = $contrato->primeiro_vencimento
            ? $contrato->primeiro_vencimento->copy()->startOfDay()
            : $contrato->data_inicio->copy()->addDays(30)->startOfDay();
        $competencia = $vencimento->copy()->startOfMonth();
        $existente = $this->mensalidades->porContratoECompetencia($contrato->id, $competencia);

        if ($existente) {
            return $existente;
        }

        $valorOriginal = (float) $contrato->valor_mensal;
        $desconto = round($valorOriginal * ((float) $contrato->desconto_percentual / 100), 2);

        return $this->mensalidades->create([
            'contrato_id' => $contrato->id,
            'empresa_id' => $contrato->empresa_id,
            'tipo' => 'mensalidade',
            'competencia' => $competencia->toDateString(),
            'valor_original' => $valorOriginal,
            'desconto' => $desconto,
            'acrescimo' => 0,
            'valor_total' => $valorOriginal - $desconto,
            'data_vencimento' => $vencimento->toDateString(),
            'status' => 'pendente',
            'observacoes' => 'Primeira mensalidade do contrato.',
        ]);
    }

    /**
     * Recalcula valor/vencimento das mensalidades abertas (pendente/atrasado)
     * após alteração de plano ou dia de vencimento no contrato.
     */
    public function sincronizarAbertasDoContrato(Contrato $contrato): void
    {
        $abertas = Mensalidade::query()
            ->where('contrato_id', $contrato->id)
            ->whereIn('status', ['pendente', 'atrasado'])
            ->get();

        $valorOriginal = (float) $contrato->valor_mensal;
        $desconto = round($valorOriginal * ((float) $contrato->desconto_percentual / 100), 2);
        $valorTotal = $valorOriginal - $desconto;
        $hoje = now()->startOfDay();

        foreach ($abertas as $mensalidade) {
            if ($mensalidade->ehCaucao()) {
                $mensalidade->update([
                    'valor_original' => $contrato->valor_caucao,
                    'desconto' => 0,
                    'valor_total' => $contrato->valor_caucao,
                ]);

                continue;
            }

            $competencia = $mensalidade->competencia->copy()->startOfMonth();
            $ehPrimeira = $contrato->primeiro_vencimento
                && $competencia->isSameMonth($contrato->primeiro_vencimento);
            $vencimento = $ehPrimeira
                ? $contrato->primeiro_vencimento->copy()
                : $competencia->copy()->day(min((int) $contrato->dia_vencimento, $competencia->daysInMonth));

            $status = $mensalidade->status;
            if ($status === 'atrasado' && $vencimento->gte($hoje)) {
                $status = 'pendente';
            } elseif ($status === 'pendente' && $vencimento->lt($hoje)) {
                $status = 'atrasado';
            }

            $mensalidade->update([
                'valor_original' => $valorOriginal,
                'desconto' => $desconto,
                'valor_total' => $valorTotal,
                'data_vencimento' => $vencimento->toDateString(),
                'status' => $status,
            ]);
        }
    }

    /**
     * Marca a mensalidade como paga e registra o pagamento. Usado tanto
     * pela baixa manual (recepção/financeiro) quanto pelo webhook do
     * Mercado Pago quando o pagamento é aprovado.
     */
    public function marcarComoPaga(
        Mensalidade $mensalidade,
        string $gateway,
        ?string $gatewayPaymentId = null,
        ?string $metodoPagamento = null,
        ?Caixa $caixa = null,
        ?int $usuarioId = null,
        ?array $payload = null,
    ): Mensalidade {
        if ($mensalidade->estaPaga()) {
            return $mensalidade;
        }

        return DB::transaction(function () use ($mensalidade, $gateway, $gatewayPaymentId, $metodoPagamento, $caixa, $usuarioId, $payload) {
            $pagamento = Pagamento::create([
                'empresa_id' => $mensalidade->empresa_id,
                'mensalidade_id' => $mensalidade->id,
                'gateway' => $gateway,
                'gateway_payment_id' => $gatewayPaymentId,
                'valor' => $mensalidade->valor_total,
                'status' => 'aprovado',
                'metodo_pagamento' => $metodoPagamento,
                'payload' => $payload,
                'pago_em' => now(),
            ]);

            $mensalidade->update([
                'status' => 'pago',
                'data_pagamento' => now()->toDateString(),
                'forma_pagamento' => $metodoPagamento,
                'gateway_transaction_id' => $gatewayPaymentId,
            ]);

            // Pagamento presencial (dinheiro/cartão na recepção) precisa entrar no caixa aberto.
            if ($caixa && $usuarioId) {
                $rotulo = $mensalidade->ehCaucao() ? 'Caução' : 'Mensalidade';

                CaixaMovimentacao::create([
                    'caixa_id' => $caixa->id,
                    'tipo' => 'entrada',
                    'categoria' => $mensalidade->ehCaucao() ? 'caucao' : 'mensalidade',
                    'descricao' => "{$rotulo} #{$mensalidade->id} - {$mensalidade->contrato->cliente->nome}",
                    'valor' => $mensalidade->valor_total,
                    'forma_pagamento' => $metodoPagamento,
                    'referencia_type' => Pagamento::class,
                    'referencia_id' => $pagamento->id,
                    'usuario_id' => $usuarioId,
                ]);
            }

            return $mensalidade;
        });
    }

    /**
     * Roda diariamente (Scheduler): mensalidades vencidas e ainda não pagas
     * passam para "atrasado", o que impacta o controle de acesso na catraca.
     */
    public function aplicarAtrasos(): int
    {
        return DB::table('mensalidades')
            ->where('status', 'pendente')
            ->whereDate('data_vencimento', '<', now()->toDateString())
            ->update(['status' => 'atrasado', 'updated_at' => now()]);
    }

    /**
     * Cancela as mensalidades futuras (ainda não vencidas) de um contrato
     * cancelado — não mexe nas já pagas nem nas já vencidas/atrasadas,
     * que continuam cobráveis normalmente.
     */
    public function cancelarPendentesFuturas(Contrato $contrato): int
    {
        return $contrato->mensalidades()
            ->where('status', 'pendente')
            ->whereDate('data_vencimento', '>=', now()->toDateString())
            ->update(['status' => 'cancelado']);
    }

    public function prorrogarProximaMensalidade(Contrato $contrato, Carbon $novaData): Mensalidade
    {
        return DB::transaction(function () use ($contrato, $novaData) {
            $mensalidade = $contrato->mensalidades()
                ->where('tipo', 'mensalidade')
                ->whereIn('status', ['pendente', 'atrasado'])
                ->orderBy('data_vencimento')
                ->lockForUpdate()
                ->first();

            if (! $mensalidade) {
                throw new \App\Exceptions\NegocioException('Este contrato não possui mensalidade aberta para prorrogar.');
            }

            if ($novaData->startOfDay()->lte($mensalidade->data_vencimento->startOfDay())) {
                throw new \App\Exceptions\NegocioException('A nova data precisa ser posterior ao vencimento atual.');
            }

            $primeiraMensalidadeId = $contrato->mensalidades()
                ->where('tipo', 'mensalidade')
                ->oldest('id')
                ->value('id');

            $mensalidade->update([
                'data_vencimento' => $novaData->toDateString(),
                'status' => 'pendente',
                'observacoes' => trim(($mensalidade->observacoes ? $mensalidade->observacoes.' ' : '').'Vencimento prorrogado em '.now()->format('d/m/Y').'.'),
            ]);

            if ($mensalidade->id === $primeiraMensalidadeId) {
                $contrato->update(['primeiro_vencimento' => $novaData->toDateString()]);
            }

            return $mensalidade->fresh();
        });
    }
}
