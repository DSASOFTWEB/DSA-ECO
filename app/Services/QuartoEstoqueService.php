<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\Produto;
use App\Models\Quarto;
use App\Models\QuartoItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Controle de itens do estoque geral emprestados (comodato) pra dentro de
 * um quarto — controle remoto, secador, jogo de toalha extra, etc. Não é
 * consumo (HospedagemConsumo, que é vendido/cobrado do hóspede): aqui o
 * item só muda de lugar e volta pro estoque geral na devolução.
 */
class QuartoEstoqueService
{
    public function __construct(protected EstoqueService $estoqueService) {}

    public function emprestar(Quarto $quarto, Produto $produto, int $quantidade, User $operador): QuartoItem
    {
        if ($quantidade <= 0) {
            throw new NegocioException('A quantidade precisa ser maior que zero.');
        }

        if (! $produto->ativo) {
            throw new NegocioException("O produto \"{$produto->nome}\" está inativo.");
        }

        return DB::transaction(function () use ($quarto, $produto, $quantidade, $operador) {
            $this->estoqueService->saidaPorComodato($produto, $quantidade, $quarto, $operador->id);

            $item = QuartoItem::firstOrNew(['quarto_id' => $quarto->id, 'produto_id' => $produto->id]);
            $item->empresa_id = $quarto->empresa_id;
            $item->quantidade = ($item->quantidade ?? 0) + $quantidade;
            $item->save();

            return $item;
        });
    }

    public function devolver(Quarto $quarto, Produto $produto, int $quantidade, User $operador): void
    {
        if ($quantidade <= 0) {
            throw new NegocioException('A quantidade precisa ser maior que zero.');
        }

        $item = QuartoItem::where('quarto_id', $quarto->id)->where('produto_id', $produto->id)->first();

        if (! $item) {
            throw new NegocioException("Não há \"{$produto->nome}\" emprestado neste quarto.");
        }

        if ($item->quantidade < $quantidade) {
            throw new NegocioException("Só há {$item->quantidade} \"{$produto->nome}\" emprestado(s) neste quarto.");
        }

        DB::transaction(function () use ($quarto, $produto, $quantidade, $operador, $item) {
            $this->estoqueService->entradaPorDevolucaoComodato($produto, $quantidade, $quarto, $operador->id);

            if ($item->quantidade > $quantidade) {
                $item->decrement('quantidade', $quantidade);
            } else {
                $item->delete();
            }
        });
    }

    /**
     * @return Collection<int, QuartoItem>
     */
    public function itensDoQuarto(Quarto $quarto): Collection
    {
        return QuartoItem::where('quarto_id', $quarto->id)->with('produto')->get();
    }

    /**
     * Visão geral pra tela "o que tem em cada quarto" — todos os quartos
     * com seus itens de comodato já carregados (quartos sem nada aparecem
     * com a lista vazia, não somem da listagem).
     *
     * @return Collection<int, Quarto>
     */
    public function visaoGeral(): Collection
    {
        return Quarto::with(['unidade', 'itensComodato.produto'])->orderBy('numero')->get();
    }
}
