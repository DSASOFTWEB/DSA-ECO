<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\Acesso;
use App\Models\Caixa;
use App\Models\CaixaMovimentacao;
use App\Models\Pagamento;
use App\Models\Produto;
use App\Models\TipoEntrada;
use App\Models\Unidade;
use App\Models\User;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Repositories\Contracts\VendaRepositoryInterface;
use Illuminate\Support\Facades\DB;

class VendaService
{
    public function __construct(
        protected VendaRepositoryInterface $vendas,
        protected EstoqueService $estoqueService,
        protected ComissaoService $comissaoService,
        protected CaixaService $caixaService,
    ) {}

    public function listar(array $filtros, int $porPagina = 15)
    {
        return $this->vendas->paginate($porPagina, ['cliente', 'vendedor', 'itens.produto', 'itens.tipoEntrada'], $filtros);
    }

    /**
     * Cada item da venda é OU um produto de estoque OU um tipo de entrada
     * avulsa (ingresso) — nunca os dois. Quando há item de entrada avulsa,
     * a venda também gera um registro de Acesso por pessoa (uma unidade de
     * quantidade = uma entrada física), satisfazendo "registrar a entrada
     * do cliente" além de "registrar a venda".
     *
     * @param  array{cliente_id?:int, observacao?:string, itens: array<array{produto_id?:int, tipo_entrada_id?:int, quantidade:int, desconto?:float}>, forma_pagamento:string}  $dados
     */
    public function criar(array $dados, User $vendedor, Caixa $caixa): Venda
    {
        if (empty($dados['itens'])) {
            throw new NegocioException('A venda precisa de ao menos um item.');
        }

        if (! $caixa->estaAberto()) {
            throw new NegocioException('Não é possível registrar uma venda sem um caixa aberto.');
        }

        return DB::transaction(function () use ($dados, $vendedor, $caixa) {
            $itensDetalhados = $this->detalharItens($dados['itens']);
            $valorBruto = collect($itensDetalhados)->sum('subtotal_bruto');
            $descontoTotal = collect($itensDetalhados)->sum('desconto');
            $valorTotal = $valorBruto - $descontoTotal;
            $temEntradaAvulsa = collect($itensDetalhados)->contains(fn ($item) => $item['tipoEntrada'] !== null);

            $venda = Venda::create([
                'empresa_id' => $vendedor->empresa_id,
                'unidade_id' => $caixa->unidade_id,
                'cliente_id' => $dados['cliente_id'] ?? null,
                'vendedor_id' => $vendedor->id,
                'caixa_id' => $caixa->id,
                'valor_bruto' => $valorBruto,
                'desconto' => $descontoTotal,
                'valor_total' => $valorTotal,
                'status' => 'pago',
                'forma_pagamento' => $dados['forma_pagamento'],
                'observacao' => $dados['observacao'] ?? null,
            ]);

            foreach ($itensDetalhados as $item) {
                $itemCriado = $venda->itens()->create([
                    'produto_id' => $item['produto']?->id,
                    'tipo_entrada_id' => $item['tipoEntrada']?->id,
                    'quantidade' => $item['quantidade'],
                    'preco_unitario' => $item['produto']?->preco_venda ?? $item['tipoEntrada']->valor,
                    'desconto' => $item['desconto'],
                    'subtotal' => $item['subtotal_bruto'] - $item['desconto'],
                ]);

                if ($item['produto']) {
                    $this->estoqueService->saidaPorVenda($item['produto'], $item['quantidade'], $venda, $vendedor->id);
                } else {
                    $this->criarAcessosAvulsos($venda, $itemCriado, $item['quantidade'], 'pdv_avulsa', $dados['cliente_id'] ?? null, $vendedor->id);
                }
            }

            $pagamento = Pagamento::create([
                'empresa_id' => $vendedor->empresa_id,
                'venda_id' => $venda->id,
                'gateway' => $dados['forma_pagamento'] === 'pix' ? 'mercadopago' : $dados['forma_pagamento'],
                'valor' => $valorTotal,
                'status' => 'aprovado',
                'metodo_pagamento' => $dados['forma_pagamento'],
                'pago_em' => now(),
            ]);

            CaixaMovimentacao::create([
                'caixa_id' => $caixa->id,
                'tipo' => 'entrada',
                'categoria' => $temEntradaAvulsa ? 'entrada_avulsa' : 'venda',
                'descricao' => "Venda #{$venda->id}",
                'valor' => $valorTotal,
                'forma_pagamento' => $dados['forma_pagamento'],
                'referencia_type' => Pagamento::class,
                'referencia_id' => $pagamento->id,
                'usuario_id' => $vendedor->id,
            ]);

            $this->comissaoService->calcularParaVenda($venda);

            return $venda->fresh(['itens.produto', 'itens.tipoEntrada', 'pagamentos']);
        });
    }

    /**
     * Cria a venda de entrada avulsa ONLINE (link público de autoatendimento,
     * sem operador logado) ainda pendente de pagamento. O id retornado é
     * usado como external_reference da cobrança Pix no Mercado Pago — por
     * isso a venda precisa existir ANTES de criar a cobrança (ver
     * registrarPagamentoPixPendente() para o passo seguinte). Nada de
     * estoque/comissão/caixa/ingresso aqui ainda — só existe de fato quando
     * o webhook do Mercado Pago confirmar o pagamento (confirmarPagamentoOnline()).
     */
    public function criarVendaOnlinePendente(Unidade $unidade, TipoEntrada $tipoEntrada, int $quantidade): Venda
    {
        if (! $tipoEntrada->ativo || (int) $tipoEntrada->empresa_id !== (int) $unidade->empresa_id) {
            throw new NegocioException('Tipo de entrada indisponível.');
        }

        if ($quantidade < 1 || $quantidade > 20) {
            throw new NegocioException('Quantidade inválida.');
        }

        $valorTotal = $tipoEntrada->valor * $quantidade;

        return DB::transaction(function () use ($unidade, $tipoEntrada, $quantidade, $valorTotal) {
            $venda = Venda::create([
                'empresa_id' => $unidade->empresa_id,
                'unidade_id' => $unidade->id,
                'vendedor_id' => null,
                'valor_bruto' => $valorTotal,
                'desconto' => 0,
                'valor_total' => $valorTotal,
                'status' => 'pendente',
                'forma_pagamento' => 'pix',
                'observacao' => 'Compra online via link público de autoatendimento',
            ]);

            $venda->itens()->create([
                'tipo_entrada_id' => $tipoEntrada->id,
                'quantidade' => $quantidade,
                'preco_unitario' => $tipoEntrada->valor,
                'desconto' => 0,
                'subtotal' => $valorTotal,
            ]);

            return $venda->fresh(['itens.tipoEntrada']);
        });
    }

    /**
     * Guarda a cobrança Pix (QR Code) já criada no Mercado Pago para uma
     * venda online pendente, para que a tela de pagamento possa ser
     * recarregada/reaberta sem precisar gerar uma cobrança nova.
     */
    public function registrarPagamentoPixPendente(Venda $venda, ?string $gatewayPaymentId, array $payload): Pagamento
    {
        return Pagamento::create([
            'empresa_id' => $venda->empresa_id,
            'venda_id' => $venda->id,
            'gateway' => 'mercadopago',
            'gateway_payment_id' => $gatewayPaymentId,
            'valor' => $venda->valor_total,
            'status' => 'pendente',
            'metodo_pagamento' => 'pix',
            'payload' => $payload,
        ]);
    }

    /**
     * Confirma (idempotente) o pagamento de uma venda online assim que o
     * webhook do Mercado Pago avisa que o Pix foi aprovado: gera os
     * ingressos (um Acesso por unidade comprada) e lança o valor no caixa
     * aberto da unidade. Se não houver caixa aberto no momento (ex.: o
     * pagamento caiu de madrugada, com o estabelecimento fechado), a venda
     * fica paga e os ingressos já valem, mas o lançamento no caixa fica
     * pendente — é conciliado automaticamente na próxima abertura de caixa
     * da unidade (ver CaixaService::abrir()).
     */
    public function confirmarPagamentoOnline(Venda $venda, string $gatewayPaymentId, array $payloadGateway): Venda
    {
        if ($venda->status === 'pago') {
            return $venda;
        }

        return DB::transaction(function () use ($venda, $gatewayPaymentId, $payloadGateway) {
            $venda->update(['status' => 'pago']);

            Pagamento::where('venda_id', $venda->id)->update([
                'status' => 'aprovado',
                'gateway_payment_id' => $gatewayPaymentId,
                'payload' => $payloadGateway,
                'pago_em' => now(),
            ]);

            foreach ($venda->itens as $item) {
                if ($item->tipo_entrada_id) {
                    $this->criarAcessosAvulsos($venda, $item, $item->quantidade, 'online', null, null);
                }
            }

            $caixa = $this->caixaService->caixaAbertoDaUnidade($venda->unidade);

            if ($caixa) {
                $this->lancarVendaNoCaixa($venda, $caixa, $caixa->usuario_abertura_id);
            }

            return $venda->fresh(['itens.tipoEntrada', 'pagamentos']);
        });
    }

    /**
     * Lança o valor de uma venda já paga (avulsa online, reconciliada depois
     * do fato) no caixa informado — usado tanto na confirmação do webhook
     * quanto na conciliação automática ao abrir o caixa do dia.
     */
    public function lancarVendaNoCaixa(Venda $venda, Caixa $caixa, int $usuarioId): void
    {
        $pagamento = $venda->pagamentos()->latest()->first();

        $this->caixaService->registrarMovimentacao($caixa, [
            'tipo' => 'entrada',
            'categoria' => 'entrada_avulsa',
            'descricao' => "Venda online #{$venda->id}",
            'valor' => $venda->valor_total,
            'forma_pagamento' => $venda->forma_pagamento,
            'referencia_type' => $pagamento ? Pagamento::class : null,
            'referencia_id' => $pagamento?->id,
            'usuario_id' => $usuarioId,
        ]);

        $venda->update(['caixa_id' => $caixa->id]);
    }

    /**
     * Vendas online pagas enquanto a unidade estava sem caixa aberto (ex.:
     * cliente comprou de madrugada, com o estabelecimento fechado) ficam com
     * caixa_id nulo até aqui — chamado logo após abrir um novo caixa, para
     * que esse dinheiro entre no caixa do dia assim que alguém o abrir.
     */
    public function reconciliarVendasOnlinePendentes(Caixa $caixa): int
    {
        $vendas = Venda::where('unidade_id', $caixa->unidade_id)
            ->where('status', 'pago')
            ->whereNull('caixa_id')
            ->get();

        foreach ($vendas as $venda) {
            $this->lancarVendaNoCaixa($venda, $caixa, $caixa->usuario_abertura_id);
        }

        return $vendas->count();
    }

    protected function criarAcessosAvulsos(Venda $venda, VendaItem $vendaItem, int $quantidade, string $origem, ?int $clienteId, ?int $registradoPorId): void
    {
        for ($i = 0; $i < $quantidade; $i++) {
            Acesso::create([
                'cliente_id' => $clienteId,
                'venda_id' => $venda->id,
                'venda_item_id' => $vendaItem->id,
                'unidade_id' => $venda->unidade_id,
                'tipo' => 'entrada',
                'origem' => $origem,
                // Todo acesso recebe um código de validação, mesmo o
                // vendido presencialmente no PDV — é o que aparece como QR/
                // código de barras no comprovante/voucher (ver
                // VendaController::comprovante e VoucherEntradaPdfExport).
                // Só a EXIGÊNCIA de validar na portaria varia por origem
                // (Acesso::ORIGENS_QUE_EXIGEM_VALIDACAO); o código em si
                // serve pra qualquer venda ter um comprovante escaneável.
                'codigo_validacao' => Acesso::gerarCodigoValidacao(),
                'autorizado' => true,
                'registrado_por_id' => $registradoPorId,
                'registrado_em' => now(),
            ]);
        }
    }

    public function cancelar(Venda $venda, int $usuarioId): Venda
    {
        if ($venda->status === 'cancelado') {
            throw new NegocioException('Esta venda já está cancelada.');
        }

        return DB::transaction(function () use ($venda, $usuarioId) {
            foreach ($venda->itens as $item) {
                if ($item->produto_id) {
                    $this->estoqueService->entrada($item->produto, $item->quantidade, "Estorno da venda #{$venda->id}", $usuarioId);
                }
            }

            $venda->update(['status' => 'cancelado']);

            return $venda;
        });
    }

    protected function detalharItens(array $itens): array
    {
        return collect($itens)->map(function (array $item) {
            $quantidade = (int) $item['quantidade'];
            $desconto = (float) ($item['desconto'] ?? 0);

            if (! empty($item['tipo_entrada_id'])) {
                $tipoEntrada = TipoEntrada::findOrFail($item['tipo_entrada_id']);

                if (! $tipoEntrada->ativo) {
                    throw new NegocioException("O tipo de entrada \"{$tipoEntrada->nome}\" está inativo.");
                }

                return [
                    'produto' => null,
                    'tipoEntrada' => $tipoEntrada,
                    'quantidade' => $quantidade,
                    'desconto' => $desconto,
                    'subtotal_bruto' => $tipoEntrada->valor * $quantidade,
                ];
            }

            $produto = Produto::findOrFail($item['produto_id']);

            if (! $produto->ativo) {
                throw new NegocioException("O produto \"{$produto->nome}\" está inativo.");
            }

            return [
                'produto' => $produto,
                'tipoEntrada' => null,
                'quantidade' => $quantidade,
                'desconto' => $desconto,
                'subtotal_bruto' => $produto->preco_venda * $quantidade,
            ];
        })->all();
    }
}
