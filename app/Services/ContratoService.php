<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Plano;
use App\Models\Unidade;
use App\Repositories\Contracts\ContratoRepositoryInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ContratoService
{
    public function __construct(
        protected ContratoRepositoryInterface $contratos,
        protected MensalidadeService $mensalidadeService,
        protected CarteirinhaService $carteirinhaService,
        protected ComissaoService $comissaoService,
    ) {}

    public function listar(array $filtros, int $porPagina = 15)
    {
        return $this->contratos->paginate($porPagina, ['cliente', 'plano', 'unidade', 'vendedor'], $filtros);
    }

    /**
     * Fluxo completo de contratação: cria o contrato, gera a primeira
     * mensalidade, emite a carteirinha do titular (e dos dependentes
     * vinculados) e calcula a comissão do vendedor — tudo atômico.
     */
    public function contratar(array $dados, ?Plano $plano = null): Contrato
    {
        $plano ??= Plano::findOrFail($dados['plano_id']);
        $cliente = Cliente::findOrFail($dados['cliente_id']);
        $unidade = Unidade::findOrFail($dados['unidade_id']);

        // Defesa em profundidade contra vazamento entre tenants: mesmo que a
        // validação de entrada falhe em barrar um id de outra empresa (ex.:
        // uma regra "exists" comum, sem escopo de tenant), o Service nunca
        // grava um contrato cujo cliente/plano/unidade não sejam da mesma
        // empresa — isso é o que realmente impede o vazamento de dados.
        if ($plano->empresa_id !== $cliente->empresa_id || $unidade->empresa_id !== $cliente->empresa_id) {
            throw new NegocioException('Cliente, plano e unidade precisam pertencer à mesma empresa.');
        }

        if (! $plano->ativo) {
            throw new NegocioException('Este plano está inativo e não pode receber novos contratos.');
        }

        $dependentesIds = $dados['dependentes'] ?? [];
        unset($dados['dependentes']);

        if ($plano->max_dependentes < count($dependentesIds)) {
            throw new NegocioException("O plano \"{$plano->nome}\" permite no máximo {$plano->max_dependentes} dependente(s).");
        }

        return DB::transaction(function () use ($dados, $plano, $cliente, $dependentesIds) {
            // empresa_id vem sempre do cliente (fonte da verdade), nunca de
            // preenchimento implícito via usuário autenticado — assim o
            // contrato fica correto mesmo criado fora de um contexto HTTP
            // (ex.: importação em lote, comando artisan, teste automatizado).
            $dados['empresa_id'] = $cliente->empresa_id;
            $dados['valor_mensal'] = $dados['valor_mensal'] ?? $plano->valor;
            $dados['numero_contrato'] = $this->gerarNumeroContrato();
            $dados['status'] = 'ativo';

            $contrato = $this->contratos->create($dados);

            if ($dependentesIds) {
                $contrato->dependentes()->sync($dependentesIds);
            }

            $competencia = Carbon::parse($contrato->data_inicio)->startOfMonth();
            $this->mensalidadeService->gerarParaContrato($contrato, $competencia);

            $this->carteirinhaService->emitirParaCliente($contrato->cliente);
            foreach ($contrato->dependentes as $dependente) {
                $this->carteirinhaService->emitirParaDependente($dependente);
            }

            $this->comissaoService->calcularParaContrato($contrato);

            return $contrato->fresh(['cliente', 'plano', 'dependentes', 'mensalidades']);
        });
    }

    public function atualizar(Contrato $contrato, array $dados): Contrato
    {
        if (! $contrato->estaAtivo()) {
            throw new NegocioException('Somente contratos ativos podem ser editados.');
        }

        return DB::transaction(fn () => $this->contratos->update($contrato, $dados));
    }

    public function cancelar(Contrato $contrato, string $motivo): Contrato
    {
        if ($contrato->status === 'cancelado') {
            throw new NegocioException('Este contrato já está cancelado.');
        }

        return DB::transaction(function () use ($contrato, $motivo) {
            $contrato->update([
                'status' => 'cancelado',
                'motivo_cancelamento' => $motivo,
                'cancelado_em' => now(),
            ]);

            $this->mensalidadeService->cancelarPendentesFuturas($contrato);

            return $contrato;
        });
    }

    /**
     * Reativa um contrato cancelado: volta pra "ativo", gera a mensalidade
     * da competência atual (mesmo passo que a contratação inicial faz) e
     * calcula a comissão de reativação do vendedor (percentual próprio,
     * separado do de venda/contrato novo — ver ComissaoService).
     */
    public function reativar(Contrato $contrato): Contrato
    {
        if (! $contrato->estaCancelado()) {
            throw new NegocioException('Somente contratos cancelados podem ser reativados.');
        }

        return DB::transaction(function () use ($contrato) {
            $contrato->update([
                'status' => 'ativo',
                'reativado_em' => now(),
            ]);

            $competencia = now()->startOfMonth();
            $this->mensalidadeService->gerarParaContrato($contrato, $competencia);

            $this->comissaoService->calcularParaReativacao($contrato);

            return $contrato->fresh(['cliente', 'plano', 'mensalidades']);
        });
    }

    protected function gerarNumeroContrato(): string
    {
        return 'CTR-'.now()->format('Ymd').'-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }
}
