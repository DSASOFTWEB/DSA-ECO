<?php

namespace App\Support;

/**
 * Tabelas fiscais oficiais usadas no cadastro de produto e na emissão
 * (CST/CSOSN ICMS, CST PIS/COFINS entrada×saída, tPag NFC-e/NF-e).
 * Espelha a documentação fiscal / padrão Evora.
 */
class FiscalTabelas
{
    /** Regime Normal — CST ICMS (Tabela B). */
    public static function cstIcms(): array
    {
        return [
            '00' => '00 — Tributada integralmente',
            '10' => '10 — Tributada e com cobrança do ICMS por ST',
            '20' => '20 — Com redução de base de cálculo',
            '30' => '30 — Isenta/não tributada e com cobrança do ICMS por ST',
            '40' => '40 — Isenta',
            '41' => '41 — Não tributada',
            '50' => '50 — Suspensão',
            '51' => '51 — Diferimento',
            '60' => '60 — ICMS cobrado anteriormente por ST',
            '61' => '61 — ICMS monofásico sobre combustíveis',
            '70' => '70 — Com redução de BC e cobrança do ICMS por ST',
            '90' => '90 — Outras',
        ];
    }

    /** Simples Nacional — CSOSN. */
    public static function csosn(): array
    {
        return [
            '101' => '101 — Tributada pelo SN com permissão de crédito',
            '102' => '102 — Tributada pelo SN sem permissão de crédito',
            '103' => '103 — Isenção do ICMS no SN para faixa de receita bruta',
            '201' => '201 — Tributada pelo SN com crédito e com cobrança de ST',
            '202' => '202 — Tributada pelo SN sem crédito e com cobrança de ST',
            '203' => '203 — Isenção no SN para faixa de receita e com ST',
            '300' => '300 — Imune',
            '400' => '400 — Não tributada pelo SN',
            '500' => '500 — ICMS cobrado anteriormente por ST ou antecipação',
            '900' => '900 — Outros',
        ];
    }

    /**
     * CST PIS/COFINS de saída (operações de venda) — 01 a 09, 49, 99.
     */
    public static function cstPisCofinsSaida(): array
    {
        return [
            '01' => '01 — Operação tributável com alíquota básica',
            '02' => '02 — Operação tributável com alíquota diferenciada',
            '03' => '03 — Operação tributável com alíquota por unidade de medida',
            '04' => '04 — Operação tributável monofásica — alíquota zero',
            '05' => '05 — Operação tributável por substituição tributária',
            '06' => '06 — Operação tributável a alíquota zero',
            '07' => '07 — Operação isenta da contribuição',
            '08' => '08 — Operação sem incidência da contribuição',
            '09' => '09 — Operação com suspensão da contribuição',
            '49' => '49 — Outras operações de saída',
            '99' => '99 — Outras operações',
        ];
    }

    /**
     * CST PIS/COFINS de entrada (compras / crédito) — 50 a 75, 98, 99.
     */
    public static function cstPisCofinsEntrada(): array
    {
        return [
            '50' => '50 — Operação com direito a crédito — vinculada exclusivamente a receita tributada no mercado interno',
            '51' => '51 — Operação com direito a crédito — vinculada exclusivamente a receita não tributada no mercado interno',
            '52' => '52 — Operação com direito a crédito — vinculada exclusivamente a receita de exportação',
            '53' => '53 — Operação com direito a crédito — vinculada a receitas tributadas e não-tributadas no mercado interno',
            '54' => '54 — Operação com direito a crédito — vinculada a receitas tributadas no mercado interno e de exportação',
            '55' => '55 — Operação com direito a crédito — vinculada a receitas não-tributadas no mercado interno e de exportação',
            '56' => '56 — Operação com direito a crédito — vinculada a receitas tributadas e não-tributadas no mercado interno e de exportação',
            '60' => '60 — Crédito presumido — operação de aquisição vinculada exclusivamente a receita tributada no mercado interno',
            '61' => '61 — Crédito presumido — operação de aquisição vinculada exclusivamente a receita não-tributada no mercado interno',
            '62' => '62 — Crédito presumido — operação de aquisição vinculada exclusivamente a receita de exportação',
            '63' => '63 — Crédito presumido — operação de aquisição vinculada a receitas tributadas e não-tributadas no mercado interno',
            '64' => '64 — Crédito presumido — operação de aquisição vinculada a receitas tributadas no mercado interno e de exportação',
            '65' => '65 — Crédito presumido — operação de aquisição vinculada a receitas não-tributadas no mercado interno e de exportação',
            '66' => '66 — Crédito presumido — operação de aquisição vinculada a receitas tributadas e não-tributadas no mercado interno e de exportação',
            '67' => '67 — Crédito presumido — outras operações',
            '70' => '70 — Operação de aquisição sem direito a crédito',
            '71' => '71 — Operação de aquisição com isenção',
            '72' => '72 — Operação de aquisição com suspensão',
            '73' => '73 — Operação de aquisição a alíquota zero',
            '74' => '74 — Operação de aquisição sem incidência da contribuição',
            '75' => '75 — Operação de aquisição por substituição tributária',
            '98' => '98 — Outras operações de entrada',
            '99' => '99 — Outras operações',
        ];
    }

    /** Origem da mercadoria (tag orig). */
    public static function origemMercadoria(): array
    {
        return [
            0 => '0 — Nacional',
            1 => '1 — Estrangeira — importação direta',
            2 => '2 — Estrangeira — adquirida no mercado interno',
            3 => '3 — Nacional com conteúdo de importação superior a 40%',
            4 => '4 — Nacional cuja produção tenha sido feita em conformidade com os processos produtivos básicos',
            5 => '5 — Nacional com conteúdo de importação inferior ou igual a 40%',
            6 => '6 — Estrangeira — importação direta, sem similar nacional, constante em lista da CAMEX',
            7 => '7 — Estrangeira — adquirida no mercado interno, sem similar nacional, constante em lista da CAMEX',
            8 => '8 — Nacional com conteúdo de importação superior a 70%',
        ];
    }

    /**
     * Formas de pagamento oficiais (tPag) — Manual de Orientação do Contribuinte NF-e/NFC-e.
     *
     * @return array<string, string>
     */
    public static function formasPagamento(): array
    {
        return [
            '01' => '01 — Dinheiro',
            '02' => '02 — Cheque',
            '03' => '03 — Cartão de Crédito',
            '04' => '04 — Cartão de Débito',
            '05' => '05 — Crédito Loja',
            '10' => '10 — Vale Alimentação',
            '11' => '11 — Vale Refeição',
            '12' => '12 — Vale Presente',
            '13' => '13 — Vale Combustível',
            '15' => '15 — Boleto Bancário',
            '16' => '16 — Depósito Bancário',
            '17' => '17 — Pagamento Instantâneo (PIX)',
            '18' => '18 — Transferência bancária, Carteira Digital',
            '19' => '19 — Programa de fidelidade, Cashback, Crédito Virtual',
            '20' => '20 — Pagamento Instantâneo (PIX) — Estático',
            '21' => '21 — Crédito em Loja por Devolução',
            '22' => '22 — Falha de hardware do sistema emissor',
            '90' => '90 — Sem pagamento',
            '99' => '99 — Outros',
        ];
    }

    /**
     * Lista ICMS conforme regime da empresa (Simples → CSOSN; Normal → CST).
     *
     * @return array{campo: string, opcoes: array<string, string>, rotulo: string}
     */
    public static function classificacaoIcmsParaRegime(?string $regime): array
    {
        $simples = in_array($regime, [
            \App\Models\Empresa::REGIME_SIMPLES,
            \App\Models\Empresa::REGIME_SIMPLES_EXCESSO,
            \App\Models\Empresa::REGIME_MEI,
        ], true);

        if ($simples) {
            return [
                'campo' => 'csosn',
                'opcoes' => self::csosn(),
                'rotulo' => 'CSOSN (Simples Nacional)',
            ];
        }

        return [
            'campo' => 'cst_icms',
            'opcoes' => self::cstIcms(),
            'rotulo' => 'CST ICMS (Regime Normal)',
        ];
    }
}
