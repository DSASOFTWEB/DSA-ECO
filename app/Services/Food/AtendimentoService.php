<?php

namespace App\Services\Food;

use App\Exceptions\NegocioException;
use App\Models\Atendimento;
use App\Models\AtendimentoItem;
use App\Models\AtendimentoTransferencia;
use App\Models\Caixa;
use App\Models\CaixaMovimentacao;
use App\Models\Pagamento;
use App\Models\PontoAtendimento;
use App\Models\Produto;
use App\Models\User;
use App\Models\Venda;
use App\Services\ComissaoService;
use App\Services\EstoqueService;
use Illuminate\Support\Facades\DB;

class AtendimentoService
{
    public function __construct(
        protected EstoqueService $estoqueService,
        protected ComissaoService $comissaoService,
    ) {}

    public function abrir(PontoAtendimento $ponto, User $operador, int $pessoas = 1): Atendimento
    {
        return DB::transaction(function () use ($ponto, $operador, $pessoas) {
            $ponto = PontoAtendimento::whereKey($ponto->id)->lockForUpdate()->firstOrFail();
            $this->validarMesmoTenantEUnidade($ponto, $operador);

            if (! in_array($ponto->status, ['livre', 'reservada'], true)) {
                throw new NegocioException("{$ponto->identificacao} não está disponível para abertura.");
            }

            if ($ponto->atendimentos()->whereIn('status', ['aberto', 'pre_fechado'])->exists()) {
                throw new NegocioException('Já existe um atendimento em andamento neste ponto.');
            }

            $atendimento = Atendimento::create([
                'empresa_id' => $ponto->empresa_id,
                'unidade_id' => $ponto->unidade_id,
                'ponto_atendimento_id' => $ponto->id,
                'aberto_por_id' => $operador->id,
                'status' => 'aberto',
                'quantidade_pessoas' => max(1, $pessoas),
                'aberto_em' => now(),
            ]);

            $ponto->update(['status' => 'ocupada']);

            return $atendimento;
        });
    }

    public function configurarPonto(PontoAtendimento $ponto, array $dados, User $operador): PontoAtendimento
    {
        return DB::transaction(function () use ($ponto, $dados, $operador) {
            $ponto = PontoAtendimento::whereKey($ponto->id)->lockForUpdate()->firstOrFail();
            $this->validarMesmoTenantEUnidade($ponto, $operador);

            if ($ponto->atendimentos()->whereIn('status', ['aberto', 'pre_fechado'])->exists()) {
                throw new NegocioException('Não é possível alterar uma mesa ou comanda com atendimento em andamento.');
            }

            $ponto->update([
                'numero' => $dados['numero'],
                'nome' => $dados['nome'] ?? null,
                'capacidade' => $dados['capacidade'] ?? null,
                'ordem' => $dados['ordem'] ?? 0,
                'status' => $dados['status'],
            ]);

            return $ponto->fresh();
        });
    }

    public function adicionarItem(Atendimento $atendimento, array $dados, User $operador): AtendimentoItem
    {
        return DB::transaction(function () use ($atendimento, $dados, $operador) {
            $atendimento = Atendimento::whereKey($atendimento->id)->lockForUpdate()->firstOrFail();
            $this->validarOperavel($atendimento, $operador);
            $produto = Produto::whereKey($dados['produto_id'])->firstOrFail();

            if ($produto->empresa_id !== $atendimento->empresa_id
                || ($produto->unidade_id && $produto->unidade_id !== $atendimento->unidade_id)
                || ! $produto->ativo) {
                throw new NegocioException('Produto indisponível para esta unidade.');
            }

            $quantidade = (int) $dados['quantidade'];
            $desconto = round((float) ($dados['desconto'] ?? 0), 2);
            $bruto = round((float) $produto->preco_venda * $quantidade, 2);

            if ($desconto > $bruto) {
                throw new NegocioException('O desconto do item não pode superar seu valor bruto.');
            }

            $item = $atendimento->itens()->create([
                'produto_id' => $produto->id,
                'lancado_por_id' => $operador->id,
                'quantidade' => $quantidade,
                'preco_unitario' => $produto->preco_venda,
                'desconto' => $desconto,
                'subtotal' => $bruto - $desconto,
                'status' => 'ativo',
                'observacao' => $dados['observacao'] ?? null,
            ]);

            $this->recalcular($atendimento);

            return $item;
        });
    }

    public function cancelarItem(AtendimentoItem $item, User $operador, string $motivo): void
    {
        DB::transaction(function () use ($item, $operador, $motivo) {
            $item = AtendimentoItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $atendimento = Atendimento::whereKey($item->atendimento_id)->lockForUpdate()->firstOrFail();
            $this->validarOperavel($atendimento, $operador);

            if ($item->status !== 'ativo') {
                throw new NegocioException('Este item já foi cancelado.');
            }

            $item->update([
                'status' => 'cancelado',
                'cancelado_por_id' => $operador->id,
                'motivo_cancelamento' => $motivo,
                'cancelado_em' => now(),
            ]);
            $this->recalcular($atendimento);
        });
    }

    public function preFechar(Atendimento $atendimento, User $operador): Atendimento
    {
        $this->validarOperavel($atendimento, $operador);
        $atendimento->update(['status' => 'pre_fechado', 'pre_fechado_em' => now()]);

        return $atendimento;
    }

    public function transferir(Atendimento $atendimento, PontoAtendimento $destino, User $operador): Atendimento
    {
        return DB::transaction(function () use ($atendimento, $destino, $operador) {
            $ids = collect([$atendimento->ponto_atendimento_id, $destino->id])->sort()->values();
            $pontos = PontoAtendimento::whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');
            $origem = $pontos->get($atendimento->ponto_atendimento_id);
            $destino = $pontos->get($destino->id);
            $atendimento = Atendimento::whereKey($atendimento->id)->lockForUpdate()->firstOrFail();
            $this->validarOperavel($atendimento, $operador);

            if (! $origem || ! $destino || $origem->id === $destino->id
                || $destino->empresa_id !== $atendimento->empresa_id
                || $destino->unidade_id !== $atendimento->unidade_id) {
                throw new NegocioException('O destino da transferência é inválido.');
            }
            if (in_array($destino->status, ['reservada', 'bloqueada'], true)) {
                throw new NegocioException('O destino está reservado ou bloqueado.');
            }

            $destinoAtendimento = $destino->atendimentos()->whereIn('status', ['aberto', 'pre_fechado'])->lockForUpdate()->first();
            $tipo = $destinoAtendimento ? 'uniao' : 'integral';

            if ($destinoAtendimento) {
                $atendimento->itens()->where('status', 'ativo')->update(['atendimento_id' => $destinoAtendimento->id]);
                $this->recalcular($destinoAtendimento);
                $atendimento->update(['status' => 'transferido']);
            } else {
                $atendimento->update(['ponto_atendimento_id' => $destino->id, 'status' => 'aberto', 'pre_fechado_em' => null]);
                $destinoAtendimento = $atendimento;
                $destino->update(['status' => 'ocupada']);
            }

            $origem->update(['status' => 'livre']);
            AtendimentoTransferencia::create([
                'empresa_id' => $atendimento->empresa_id,
                'atendimento_origem_id' => $atendimento->id,
                'atendimento_destino_id' => $destinoAtendimento->id,
                'ponto_origem_id' => $origem->id,
                'ponto_destino_id' => $destino->id,
                'usuario_id' => $operador->id,
                'tipo' => $tipo,
                'detalhes' => ['origem' => $origem->identificacao, 'destino' => $destino->identificacao],
            ]);

            return $destinoAtendimento->fresh(['ponto', 'itens.produto']);
        });
    }

    public function fechar(Atendimento $atendimento, array $dados, User $operador): Atendimento
    {
        return DB::transaction(function () use ($atendimento, $dados, $operador) {
            $atendimento = Atendimento::whereKey($atendimento->id)->lockForUpdate()->firstOrFail();
            if ($atendimento->status === 'fechado' && $atendimento->venda_id) {
                return $atendimento;
            }
            $this->validarOperavel($atendimento, $operador);
            $itens = $atendimento->itens()->where('status', 'ativo')->with('produto')->lockForUpdate()->get();
            if ($itens->isEmpty()) {
                throw new NegocioException('Não é possível fechar uma conta sem itens.');
            }

            $caixa = Caixa::whereKey($dados['caixa_id'])->lockForUpdate()->firstOrFail();
            if (! $caixa->estaAberto() || $caixa->empresa_id !== $atendimento->empresa_id || $caixa->unidade_id !== $atendimento->unidade_id) {
                throw new NegocioException('Selecione um caixa aberto da mesma unidade.');
            }

            $subtotal = round((float) $itens->sum('subtotal'), 2);
            $desconto = round((float) ($dados['desconto'] ?? 0), 2);
            $percentualServico = round((float) ($dados['percentual_servico'] ?? 0), 2);
            $valorServico = round($subtotal * ($percentualServico / 100), 2);
            $total = round($subtotal + $valorServico - $desconto, 2);

            if ($total < 0 || $desconto > $subtotal + $valorServico) {
                throw new NegocioException('O desconto informado é inválido.');
            }
            $totalPagamentos = round(collect($dados['pagamentos'])->sum(fn ($p) => (float) $p['valor']), 2);
            if (abs($totalPagamentos - $total) > 0.009) {
                throw new NegocioException('A soma dos pagamentos deve ser igual ao total da conta.');
            }

            $formas = collect($dados['pagamentos'])->pluck('forma')->unique();
            $venda = Venda::create([
                'empresa_id' => $atendimento->empresa_id,
                'unidade_id' => $atendimento->unidade_id,
                'cliente_id' => $atendimento->cliente_id,
                'vendedor_id' => $operador->id,
                'caixa_id' => $caixa->id,
                'valor_bruto' => $subtotal + $valorServico,
                'desconto' => $desconto,
                'valor_total' => $total,
                'status' => 'pago',
                'forma_pagamento' => $formas->count() === 1 ? $formas->first() : 'multiplo',
                'observacao' => "Conta {$atendimento->ponto->identificacao}; taxa de serviço: {$percentualServico}%",
            ]);

            foreach ($itens as $item) {
                $venda->itens()->create([
                    'produto_id' => $item->produto_id,
                    'quantidade' => $item->quantidade,
                    'preco_unitario' => $item->preco_unitario,
                    'desconto' => $item->desconto,
                    'subtotal' => $item->subtotal,
                ]);
                $this->estoqueService->saidaPorVenda($item->produto, $item->quantidade, $venda, $operador->id);
            }

            foreach ($dados['pagamentos'] as $parcela) {
                $pagamento = Pagamento::create([
                    'empresa_id' => $atendimento->empresa_id,
                    'venda_id' => $venda->id,
                    'gateway' => $parcela['forma'] === 'pix' ? 'mercadopago' : $parcela['forma'],
                    'valor' => $parcela['valor'],
                    'status' => 'aprovado',
                    'metodo_pagamento' => $parcela['forma'],
                    'pago_em' => now(),
                ]);
                CaixaMovimentacao::create([
                    'caixa_id' => $caixa->id,
                    'tipo' => 'entrada',
                    'categoria' => 'food',
                    'descricao' => "Fechamento {$atendimento->ponto->identificacao} - atendimento #{$atendimento->id}",
                    'valor' => $parcela['valor'],
                    'forma_pagamento' => $parcela['forma'],
                    'referencia_type' => Pagamento::class,
                    'referencia_id' => $pagamento->id,
                    'usuario_id' => $operador->id,
                ]);
            }

            $this->comissaoService->calcularParaVenda($venda);
            $atendimento->update([
                'venda_id' => $venda->id,
                'fechado_por_id' => $operador->id,
                'status' => 'fechado',
                'subtotal' => $subtotal,
                'desconto' => $desconto,
                'percentual_servico' => $percentualServico,
                'valor_servico' => $valorServico,
                'valor_total' => $total,
                'fechado_em' => now(),
            ]);
            $atendimento->ponto()->update(['status' => 'livre']);

            return $atendimento->fresh(['ponto', 'itens.produto', 'venda.pagamentos']);
        });
    }

    protected function recalcular(Atendimento $atendimento): void
    {
        $subtotal = round((float) $atendimento->itens()->where('status', 'ativo')->sum('subtotal'), 2);
        $valorServico = round($subtotal * ((float) $atendimento->percentual_servico / 100), 2);
        $atendimento->update([
            'subtotal' => $subtotal,
            'valor_servico' => $valorServico,
            'valor_total' => max(0, $subtotal + $valorServico - (float) $atendimento->desconto),
        ]);
    }

    protected function validarOperavel(Atendimento $atendimento, User $operador): void
    {
        if ($atendimento->empresa_id !== $operador->empresa_id || ! $atendimento->estaEmAndamento()) {
            throw new NegocioException('Este atendimento não está disponível para operação.');
        }
    }

    protected function validarMesmoTenantEUnidade(PontoAtendimento $ponto, User $operador): void
    {
        if ($ponto->empresa_id !== $operador->empresa_id || ($operador->unidade_id && $ponto->unidade_id !== $operador->unidade_id)) {
            throw new NegocioException('O ponto de atendimento não pertence à unidade do operador.');
        }
    }
}
