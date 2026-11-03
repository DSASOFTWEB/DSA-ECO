<?php

namespace App\Services\Impressao;

/**
 * Fechamento (ou parcial) de caixa em ESC/POS para bobina 80mm: totais,
 * quebra por forma de pagamento e por categoria, conferência do dinheiro,
 * extrato resumido e linhas de assinatura.
 *
 * Recebe os dados de App\Services\Relatorios\CaixaFechamentoResumo.
 */
class CaixaEscPosBuilder extends EscPosCupomBuilder
{
    public function montarFechamento(array $r, bool $comMovimentacoes = true): string
    {
        $caixa = $r['caixa'];
        $empresa = $caixa->empresa;

        $out = $this->cmdInit();
        $out .= $this->cmdAlign(1);
        $out .= $this->cmdBold(true);
        foreach ($this->quebrar($empresa?->nome ?? $caixa->unidade?->nome ?? 'Estabelecimento') as $l) {
            $out .= $this->linha($l);
        }
        $out .= $this->cmdBold(false);
        $out .= $this->separadorDuplo();
        $out .= $this->cmdBold(true);
        $out .= $this->linha($r['aberto'] ? 'PARCIAL DE CAIXA' : 'FECHAMENTO DE CAIXA');
        $out .= $this->cmdBold(false);
        $out .= $this->linha('Caixa N. '.str_pad((string) $caixa->id, 6, '0', STR_PAD_LEFT));
        $out .= $this->cmdAlign(0);
        $out .= $this->separadorFino();

        $out .= $this->linhaPar('Unidade', (string) ($caixa->unidade?->nome ?? '-'));
        $out .= $this->linhaPar('Terminal', (string) ($caixa->terminal?->nome ?? '-'));
        $out .= $this->linhaPar('Abertura', $caixa->data_abertura->format('d/m/Y H:i'));
        $out .= $this->linhaPar('Aberto por', (string) ($caixa->usuarioAbertura?->name ?? '-'));
        if (! $r['aberto']) {
            $out .= $this->linhaPar('Fechamento', $caixa->data_fechamento?->format('d/m/Y H:i') ?? '-');
            $out .= $this->linhaPar('Fechado por', (string) ($caixa->usuarioFechamento?->name ?? '-'));
        }

        $out .= $this->separador('RESUMO');
        $out .= $this->linhaPar('Valor de abertura', $this->moeda($r['abertura']));
        $out .= $this->linhaPar('(+) Entradas', $this->moeda($r['entradas']));
        $out .= $this->linhaPar('(-) Saidas', $this->moeda($r['saidas']));
        $out .= $this->separadorFino();
        $out .= $this->cmdBold(true);
        $out .= $this->linhaPar($r['aberto'] ? 'SALDO ATE AGORA' : 'SALDO DO SISTEMA', $this->moeda($r['saldo']));
        $out .= $this->cmdBold(false);
        if (! $r['aberto']) {
            $out .= $this->linhaPar('Valor contado', $this->moeda($r['informado']));
            $out .= $this->cmdBold(true);
            $out .= $this->linhaPar('DIFERENCA', $this->sinal($r['diferenca']).$this->moeda(abs($r['diferenca'])));
            $out .= $this->cmdBold(false);
            $out .= $this->linha($this->situacao($r['diferenca']));
        }

        $out .= $this->separador('POR FORMA DE PAGAMENTO');
        foreach ($r['formas'] as $f) {
            $out .= $this->linhaPar($f['forma'], $this->moeda($f['entradas']));
            if ($f['saidas'] > 0) {
                $out .= $this->linhaPar('  saidas', '- '.$this->moeda($f['saidas']));
            }
        }
        if ($r['formas'] === []) {
            $out .= $this->linha('Nenhuma movimentacao.');
        }
        $out .= $this->separadorFino();
        $out .= $this->cmdBold(true);
        $out .= $this->linhaPar('DINHEIRO NA GAVETA', $this->moeda($r['dinheiro_gaveta']));
        $out .= $this->cmdBold(false);
        $out .= $this->linha('(abertura + dinheiro recebido - saidas)');

        $out .= $this->separador('POR CATEGORIA');
        foreach ($r['categorias'] as $c) {
            $out .= $this->linhaPar(
                ($c['tipo'] === 'saida' ? '(-) ' : '').$c['categoria'].' ('.$c['quantidade'].')',
                $this->moeda($c['total'])
            );
        }

        if ($comMovimentacoes && $r['movimentacoes']->isNotEmpty()) {
            $out .= $this->separador('MOVIMENTACOES ('.$r['movimentacoes']->count().')');
            foreach ($r['movimentacoes'] as $m) {
                $valor = ($m->tipo === 'saida' ? '-' : '+').$this->moeda($m->valor);
                $desc = $m->created_at->format('d/m H:i').' '.($m->descricao ?: ucfirst(str_replace('_', ' ', $m->categoria)));
                $out .= $this->linhaPar($this->truncar($desc, $this->colunas - mb_strlen($valor) - 1), $valor);
            }
        }

        $out .= "\n\n";
        $out .= $this->linha(str_repeat('_', min(36, $this->colunas)));
        $out .= $this->linha('Operador');
        $out .= "\n\n";
        $out .= $this->linha(str_repeat('_', min(36, $this->colunas)));
        $out .= $this->linha('Conferente');
        $out .= "\n";
        $out .= $this->cmdAlign(1);
        $out .= $this->linha('Impresso em '.now()->format('d/m/Y H:i'));
        $out .= "\n\n";
        $out .= $this->cmdCut();

        return $out;
    }

    protected function sinal(float $valor): string
    {
        return $valor > 0.004 ? '+ ' : ($valor < -0.004 ? '- ' : '');
    }

    protected function situacao(float $diferenca): string
    {
        return match (true) {
            $diferenca > 0.004 => '>> SOBRA no caixa',
            $diferenca < -0.004 => '>> FALTA no caixa',
            default => '>> Caixa conferido, sem diferenca',
        };
    }
}
