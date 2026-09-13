<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\MovimentacaoEstoque;
use App\Models\Produto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EstoqueService
{
    public function entrada(Produto $produto, int $quantidade, string $motivo, int $usuarioId): MovimentacaoEstoque
    {
        return $this->movimentar($produto, 'entrada', $quantidade, $motivo, $usuarioId);
    }

    public function ajuste(Produto $produto, int $novaQuantidade, string $motivo, int $usuarioId): MovimentacaoEstoque
    {
        $diferenca = abs($novaQuantidade - $produto->estoque_atual);

        return $this->movimentar($produto, 'ajuste', $diferenca, $motivo, $usuarioId, forcarQuantidadeFinal: $novaQuantidade);
    }

    /**
     * Dá baixa no estoque por causa de uma venda. Lança NegocioException se
     * o produto controla estoque e a quantidade disponível for insuficiente
     * — nunca permite estoque_atual negativo. A validação é feita DE NOVO
     * dentro da transação, já com o registro travado (lockForUpdate): a
     * checagem aqui fora é só para falhar rápido no caso comum; sem
     * revalidar sob lock, duas vendas simultâneas do último item em
     * estoque poderiam passar as duas e deixar o estoque negativo.
     */
    public function saidaPorVenda(Produto $produto, int $quantidade, Model $referencia, int $usuarioId): MovimentacaoEstoque
    {
        if ($produto->controla_estoque && $produto->estoque_atual < $quantidade) {
            throw new NegocioException("Estoque insuficiente para o produto \"{$produto->nome}\" (disponível: {$produto->estoque_atual}, solicitado: {$quantidade}).");
        }

        return $this->movimentar($produto, 'venda', $quantidade, 'Baixa por venda', $usuarioId, saida: true, referencia: $referencia);
    }

    /**
     * Sai do estoque geral pra ficar emprestado (comodato) num quarto —
     * mesma regra de estoque insuficiente de saidaPorVenda(), mas sem
     * vender/faturar nada. Quem mantém "quanto está emprestado em cada
     * quarto agora" é o QuartoEstoqueService (tabela `quarto_itens`); aqui
     * só entra no histórico de movimentações do produto.
     */
    public function saidaPorComodato(Produto $produto, int $quantidade, Model $referencia, int $usuarioId): MovimentacaoEstoque
    {
        if ($produto->controla_estoque && $produto->estoque_atual < $quantidade) {
            throw new NegocioException("Estoque insuficiente para o produto \"{$produto->nome}\" (disponível: {$produto->estoque_atual}, solicitado: {$quantidade}).");
        }

        return $this->movimentar($produto, 'comodato', $quantidade, 'Emprestado em comodato', $usuarioId, saida: true, referencia: $referencia);
    }

    /**
     * Devolução de um item que estava emprestado em comodato — volta pro
     * estoque geral.
     */
    public function entradaPorDevolucaoComodato(Produto $produto, int $quantidade, Model $referencia, int $usuarioId): MovimentacaoEstoque
    {
        return $this->movimentar($produto, 'devolucao_comodato', $quantidade, 'Devolução de comodato', $usuarioId, referencia: $referencia);
    }

    protected function movimentar(
        Produto $produto,
        string $tipo,
        int $quantidade,
        string $motivo,
        int $usuarioId,
        bool $saida = false,
        ?Model $referencia = null,
        ?int $forcarQuantidadeFinal = null,
    ): MovimentacaoEstoque {
        return DB::transaction(function () use ($produto, $tipo, $quantidade, $motivo, $usuarioId, $saida, $referencia, $forcarQuantidadeFinal) {
            // Lock pessimista: evita condição de corrida quando duas vendas
            // dão baixa no mesmo produto ao mesmo tempo (caixas simultâneos).
            $produto = Produto::whereKey($produto->id)->lockForUpdate()->first();

            $quantidadeAnterior = $produto->estoque_atual;
            $quantidadeAtual = $forcarQuantidadeFinal
                ?? ($saida ? $quantidadeAnterior - $quantidade : $quantidadeAnterior + $quantidade);

            // Revalidação sob lock: garante que, mesmo em concorrência, o
            // estoque nunca fique negativo (a checagem de saidaPorVenda()
            // antes da transação é só uma falha rápida no caso comum).
            if ($saida && $produto->controla_estoque && $quantidadeAtual < 0) {
                throw new NegocioException("Estoque insuficiente para o produto \"{$produto->nome}\" (disponível: {$quantidadeAnterior}, solicitado: {$quantidade}).");
            }

            $produto->update(['estoque_atual' => $quantidadeAtual]);

            return MovimentacaoEstoque::create([
                'produto_id' => $produto->id,
                'tipo' => $tipo,
                'quantidade' => $quantidade,
                'quantidade_anterior' => $quantidadeAnterior,
                'quantidade_atual' => $quantidadeAtual,
                'motivo' => $motivo,
                'referencia_type' => $referencia?->getMorphClass(),
                'referencia_id' => $referencia?->getKey(),
                'usuario_id' => $usuarioId,
            ]);
        });
    }
}
