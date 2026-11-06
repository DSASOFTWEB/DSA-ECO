<?php

namespace App\Services\Relatorios;

use App\Models\Caixa;
use App\Support\Financeiro;

/**
 * Números do fechamento de caixa para a bobina 80mm (tela e ESC/POS):
 * os totais oficiais do caixa (os mesmos do PDF) mais a quebra por forma
 * de pagamento e por categoria, e o dinheiro que deve estar na gaveta.
 */
class CaixaFechamentoResumo
{
    /**
     * @return array{
     *   caixa: Caixa, aberto: bool, abertura: float, entradas: float, saidas: float, saldo: float,
     *   informado: ?float, diferenca: ?float, dinheiro_gaveta: float,
     *   formas: list<array{forma: string, entradas: float, saidas: float}>,
     *   categorias: list<array{categoria: string, tipo: string, quantidade: int, total: float}>,
     *   movimentacoes: \Illuminate\Support\Collection
     * }
     */
    public function montar(Caixa $caixa): array
    {
        $caixa->loadMissing(['unidade', 'terminal', 'usuarioAbertura', 'usuarioFechamento', 'movimentacoes.usuario', 'empresa']);
        $movs = $caixa->movimentacoes->sortBy('created_at')->values();

        $entradas = round((float) $movs->where('tipo', 'entrada')->sum('valor'), 2);
        $saidas = round((float) $movs->where('tipo', 'saida')->sum('valor'), 2);
        $abertura = (float) $caixa->valor_abertura;
        $aberto = $caixa->estaAberto();

        $formas = $movs->groupBy(fn ($m) => $m->forma_pagamento ?: 'outro')
            ->map(fn ($grupo, $forma) => [
                'forma' => Financeiro::FORMAS_PAGAMENTO[$forma]['label'] ?? ucfirst(str_replace('_', ' ', (string) $forma)),
                'chave' => (string) $forma,
                'entradas' => round((float) $grupo->where('tipo', 'entrada')->sum('valor'), 2),
                'saidas' => round((float) $grupo->where('tipo', 'saida')->sum('valor'), 2),
            ])
            ->sortByDesc(fn ($f) => $f['entradas'])
            ->values();

        $dinheiro = $formas->firstWhere('chave', 'dinheiro');

        // Recebido por forma (gráfico do resumo): só o que entrou de cliente —
        // suprimento, transferência e ajuste não são receita.
        $recebidos = $movs->where('tipo', 'entrada')
            ->reject(fn ($m) => in_array($m->categoria, self::NAO_RECEITA, true))
            ->groupBy(fn ($m) => $m->forma_pagamento ?: 'outro')
            ->map(fn ($grupo, $forma) => [
                'chave' => (string) $forma,
                'forma' => Financeiro::FORMAS_PAGAMENTO[$forma]['label'] ?? ucfirst(str_replace('_', ' ', (string) $forma)),
                'total' => round((float) $grupo->sum('valor'), 2),
                'quantidade' => $grupo->count(),
                'cor' => self::CORES[$forma] ?? self::CORES['outro'],
            ])
            ->filter(fn ($r) => $r['total'] > 0)
            ->sortByDesc('total')
            ->values();
        $recebidoTotal = round((float) $recebidos->sum('total'), 2);
        $recebidos = $recebidos->map(fn ($r) => $r + ['percentual' => $recebidoTotal > 0 ? round($r['total'] / $recebidoTotal * 100, 1) : 0.0]);

        $categorias = $movs->groupBy(fn ($m) => $m->tipo.'|'.$m->categoria)
            ->map(fn ($grupo) => [
                'categoria' => ucfirst(str_replace('_', ' ', (string) $grupo->first()->categoria)),
                'tipo' => $grupo->first()->tipo,
                'quantidade' => $grupo->count(),
                'total' => round((float) $grupo->sum('valor'), 2),
            ])
            ->sortBy(fn ($c) => ($c['tipo'] === 'entrada' ? 0 : 1).$c['categoria'])
            ->values();

        return [
            'caixa' => $caixa,
            'aberto' => $aberto,
            'abertura' => $abertura,
            'entradas' => $entradas,
            'saidas' => $saidas,
            'saldo' => $aberto ? round($abertura + $entradas - $saidas, 2) : (float) $caixa->valor_fechamento_sistema,
            'informado' => $aberto ? null : (float) $caixa->valor_fechamento_informado,
            'diferenca' => $aberto ? null : (float) $caixa->diferenca,
            // Gaveta = troco inicial + dinheiro recebido − dinheiro que saiu (sangria, troco...).
            'dinheiro_gaveta' => round($abertura + ($dinheiro['entradas'] ?? 0) - ($dinheiro['saidas'] ?? 0), 2),
            'formas' => $formas->map(fn ($f) => array_diff_key($f, ['chave' => 1]))->all(),
            'categorias' => $categorias->all(),
            'recebimentos' => $recebidos->all(),
            'recebido_total' => $recebidoTotal,
            'movimentacoes' => $movs,
        ];
    }

    /** Entradas que não são recebimento de cliente (não vão para o gráfico). */
    public const NAO_RECEITA = ['suprimento', 'transferencia_entrada', 'ajuste'];

    /** Cor de cada forma no gráfico (mesmas cores dos botões do PDV). */
    public const CORES = [
        'dinheiro' => '#16a34a',
        'pix' => '#0d9488',
        'cartao_credito' => '#2563eb',
        'cartao_debito' => '#7c3aed',
        'cartao' => '#4f46e5',
        'transferencia' => '#f59e0b',
        'boleto' => '#db2777',
        'outro' => '#94a3b8',
    ];

    /**
     * Fundo CSS (conic-gradient) da pizza: uma fatia por forma, na ordem da lista.
     *
     * @param  list<array{percentual: float, cor: string}>  $recebimentos
     */
    public static function gradientePizza(array $recebimentos): string
    {
        if ($recebimentos === []) {
            return '#e2e8f0';
        }

        $fatias = [];
        $inicio = 0.0;
        foreach (array_values($recebimentos) as $i => $r) {
            $fim = $i === count($recebimentos) - 1 ? 100.0 : min(100.0, $inicio + $r['percentual']);
            $fatias[] = "{$r['cor']} {$inicio}% {$fim}%";
            $inicio = $fim;
        }

        return 'conic-gradient('.implode(', ', $fatias).')';
    }
}
