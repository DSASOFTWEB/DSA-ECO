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
     * Formas de pagamento do caixa/PDV, com código fiscal tPag (NF-e/NFC-e).
     * 'tipo_saldo' classifica onde o valor cai (caixa físico / banco / maquininha).
     * Chave interna estável; codigo_fiscal segue a tabela oficial do MOC.
     */
    public const FORMAS_PAGAMENTO = [
        'dinheiro' => ['label' => 'Dinheiro', 'tipo_saldo' => 'caixa_fisico', 'codigo_fiscal' => '01'],
        'pix' => ['label' => 'Pix', 'tipo_saldo' => 'banco', 'codigo_fiscal' => '17'],
        'cartao' => ['label' => 'Cartão', 'tipo_saldo' => 'maquininha', 'codigo_fiscal' => '03'],
        'cartao_credito' => ['label' => 'Cartão de crédito', 'tipo_saldo' => 'maquininha', 'codigo_fiscal' => '03'],
        'cartao_debito' => ['label' => 'Cartão de débito', 'tipo_saldo' => 'maquininha', 'codigo_fiscal' => '04'],
        'transferencia' => ['label' => 'Transferência', 'tipo_saldo' => 'banco', 'codigo_fiscal' => '18'],
        'boleto' => ['label' => 'Boleto', 'tipo_saldo' => 'banco', 'codigo_fiscal' => '15'],
        'outro' => ['label' => 'Outro', 'tipo_saldo' => 'outro', 'codigo_fiscal' => '99'],
    ];

    public static function codigoFiscalFormaPagamento(?string $chave): string
    {
        if ($chave === null || $chave === '') {
            return '99';
        }

        if (isset(self::FORMAS_PAGAMENTO[$chave]['codigo_fiscal'])) {
            return self::FORMAS_PAGAMENTO[$chave]['codigo_fiscal'];
        }

        // Já veio como tPag (01, 17…)
        if (isset(FiscalTabelas::formasPagamento()[$chave])) {
            return $chave;
        }

        return '99';
    }

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
