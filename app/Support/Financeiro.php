<?php

namespace App\Support;

/**
 * Lista única de categorias/formas de pagamento do financeiro (caixa,
 * contas a pagar/receber, transferências). Antes disso cada tela tinha sua
 * própria lista solta em HTML — isso padroniza pra todo lançamento novo
 * cair numa categoria conhecida, e permite filtrar/relatar por ela.
 * Lançamentos antigos com texto livre continuam existindo no banco; as
 * telas só param de OFERECER opções fora desta lista dqui pra frente.
 */
class Financeiro
{
    public const CATEGORIAS_ENTRADA = [
        'venda' => 'Venda',
        'entrada_avulsa' => 'Entrada avulsa',
        'mensalidade' => 'Mensalidade',
        'hospedagem' => 'Hospedagem',
        'conta_a_receber' => 'Conta a receber',
        'suprimento' => 'Suprimento',
        'transferencia_entrada' => 'Transferência entre caixas',
        'ajuste' => 'Ajuste',
        'outro' => 'Outro',
    ];

    public const CATEGORIAS_SAIDA = [
        'despesa' => 'Despesa',
        'compra' => 'Compra',
        'pagamento_fornecedor' => 'Pagamento a fornecedor',
        'sangria' => 'Sangria',
        'conta_a_pagar' => 'Conta a pagar',
        'transferencia_saida' => 'Transferência entre caixas',
        'ajuste' => 'Ajuste',
        'outro' => 'Outro',
    ];

    /**
     * 'tipo_saldo' classifica onde o valor efetivamente cai (seção 16/17 do
     * pedido do cliente: caixa físico vs banco vs maquininha) — hoje é só
     * metadado usado pra agrupar/exibir (ex: resumo por forma de pagamento
     * no card de saldo do caixa), não roteia dinheiro de verdade.
     */
    public const FORMAS_PAGAMENTO = [
        'dinheiro' => ['label' => 'Dinheiro', 'tipo_saldo' => 'caixa_fisico'],
        'pix' => ['label' => 'Pix', 'tipo_saldo' => 'banco'],
        'cartao' => ['label' => 'Cartão', 'tipo_saldo' => 'maquininha'],
        'transferencia' => ['label' => 'Transferência', 'tipo_saldo' => 'banco'],
        'boleto' => ['label' => 'Boleto', 'tipo_saldo' => 'banco'],
        'outro' => ['label' => 'Outro', 'tipo_saldo' => 'outro'],
    ];

    public static function categorias(string $tipo): array
    {
        return $tipo === 'entrada' ? self::CATEGORIAS_ENTRADA : self::CATEGORIAS_SAIDA;
    }

    public static function labelCategoria(string $tipo, string $chave): string
    {
        return self::categorias($tipo)[$chave] ?? $chave;
    }

    public static function labelFormaPagamento(?string $chave): ?string
    {
        if ($chave === null) {
            return null;
        }

        return self::FORMAS_PAGAMENTO[$chave]['label'] ?? $chave;
    }
}
