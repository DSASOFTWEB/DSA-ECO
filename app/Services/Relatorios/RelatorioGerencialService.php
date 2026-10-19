<?php

namespace App\Services\Relatorios;

use App\Models\Atendimento;
use App\Models\Caixa;
use App\Models\CaixaMovimentacao;
use App\Models\Hospedagem;
use App\Models\Mensalidade;
use App\Models\Venda;
use App\Support\Financeiro;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Relatórios gerenciais da aba Relatórios. Cada método devolve a mesma
 * estrutura, usada pela tela, pela impressão e pelo Excel:
 *
 *   resumo  => [['rotulo', 'valor', 'formato']]         cards do topo
 *   colunas => [chave => ['rotulo', 'formato']]          cabeçalho da tabela
 *   linhas  => [[chave => valor]]
 *   totais  => [chave => valor] | null                   rodapé
 *
 * Formatos: texto, numero, moeda, percentual, data, datahora.
 * Todas as consultas partem de models com BelongsToTenant (escopo da
 * empresa do usuário) e respeitam período e unidade.
 */
class RelatorioGerencialService
{
    public const RELATORIOS = [
        'faturamento' => ['titulo' => 'Faturamento geral', 'descricao' => 'Tudo que entrou no período, por canal, forma de pagamento e dia.', 'icone' => 'cash'],
        'ticket-medio' => ['titulo' => 'Ticket médio', 'descricao' => 'Valor médio por venda em cada canal (PDV, mesas, link, hospedagem).', 'icone' => 'chart'],
        'produtos' => ['titulo' => 'Vendas por produto', 'descricao' => 'Quantidade e faturamento de cada produto, com participação.', 'icone' => 'box'],
        'tipos-entrada' => ['titulo' => 'Tipos de entrada', 'descricao' => 'Ingressos vendidos por tipo, no PDV e no link externo.', 'icone' => 'ticket'],
        'vendedores' => ['titulo' => 'Vendas por vendedor', 'descricao' => 'Vendas, faturamento e ticket médio de cada operador.', 'icone' => 'users'],
        'caixas' => ['titulo' => 'Terminais e caixas', 'descricao' => 'Cada caixa do período: entradas, saídas e diferença no fechamento.', 'icone' => 'wallet'],
        'mesas' => ['titulo' => 'Faturamento de mesas', 'descricao' => 'Contas fechadas por mesa/comanda, taxa de serviço e ticket.', 'icone' => 'table'],
        'quartos' => ['titulo' => 'Quartos', 'descricao' => 'Hospedagens finalizadas por quarto: diárias, faturamento e ticket.', 'icone' => 'bed'],
        'link-externo' => ['titulo' => 'Vendas do link externo', 'descricao' => 'Compras feitas pelo link público: pagas, pendentes e canceladas.', 'icone' => 'link'],
    ];

    protected Carbon $inicio;

    protected Carbon $fim;

    protected ?int $unidadeId = null;

    public function periodo(Carbon $inicio, Carbon $fim, ?int $unidadeId = null): static
    {
        $this->inicio = $inicio->copy()->startOfDay();
        $this->fim = $fim->copy()->endOfDay();
        $this->unidadeId = $unidadeId ?: null;

        return $this;
    }

    public function gerar(string $relatorio): array
    {
        $metodo = match ($relatorio) {
            'faturamento' => 'faturamento',
            'ticket-medio' => 'ticketMedio',
            'produtos' => 'produtos',
            'tipos-entrada' => 'tiposEntrada',
            'vendedores' => 'vendedores',
            'caixas' => 'caixas',
            'mesas' => 'mesas',
            'quartos' => 'quartos',
            'link-externo' => 'linkExterno',
        };

        // União de arrays: vale a chave da esquerda — o resultado do relatório
        // vem primeiro para seus "totais" não serem trocados pelo padrão null.
        return $this->{$metodo}() + self::RELATORIOS[$relatorio] + ['totais' => null];
    }

    // ---------------------------------------------------------------- consultas base

    /** Vendas pagas no período (PDV, mesas e link externo). */
    protected function vendasPagas(): Builder
    {
        return Venda::query()
            ->where('vendas.status', 'pago')
            ->whereBetween('vendas.created_at', [$this->inicio, $this->fim])
            ->when($this->unidadeId, fn ($q, $id) => $q->where('vendas.unidade_id', $id));
    }

    protected function hospedagensFinalizadas(): Builder
    {
        return Hospedagem::query()
            ->where('hospedagens.status', 'finalizado')
            ->whereBetween('hospedagens.data_checkout_real', [$this->inicio, $this->fim])
            ->when($this->unidadeId, fn ($q, $id) => $q->where('hospedagens.unidade_id', $id));
    }

    protected function mensalidadesPagas(): Builder
    {
        return Mensalidade::query()
            ->where('mensalidades.status', 'pago')
            ->whereBetween('mensalidades.data_pagamento', [$this->inicio->toDateString(), $this->fim->toDateString()])
            ->when($this->unidadeId, fn ($q, $id) => $q->whereHas('contrato', fn ($c) => $c->where('unidade_id', $id)));
    }

    // ---------------------------------------------------------------- relatórios

    protected function faturamento(): array
    {
        $porOrigem = $this->vendasPagas()
            ->groupBy('vendas.origem')
            ->selectRaw('vendas.origem, count(*) as qtd, sum(vendas.valor_total) as total')
            ->get()->keyBy('origem');

        $hospedagem = $this->hospedagensFinalizadas()->selectRaw('count(*) as qtd, coalesce(sum(valor_total), 0) as total')->first();
        $mensalidades = $this->mensalidadesPagas()->selectRaw('count(*) as qtd, coalesce(sum(valor_total), 0) as total')->first();

        $canais = [];
        foreach (Venda::ORIGENS as $origem => $rotulo) {
            $canais[$rotulo] = ['qtd' => (int) ($porOrigem[$origem]->qtd ?? 0), 'total' => (float) ($porOrigem[$origem]->total ?? 0)];
        }
        $canais['Hospedagem'] = ['qtd' => (int) $hospedagem->qtd, 'total' => (float) $hospedagem->total];
        $canais['Mensalidades'] = ['qtd' => (int) $mensalidades->qtd, 'total' => (float) $mensalidades->total];
        $totalGeral = array_sum(array_column($canais, 'total'));

        // Por dia: vendas + hospedagens + mensalidades
        $dias = [];
        foreach ($this->vendasPagas()->groupByRaw('date(vendas.created_at), vendas.origem')->selectRaw('date(vendas.created_at) as dia, vendas.origem, sum(vendas.valor_total) as total')->get() as $r) {
            $dias[$r->dia][$r->origem] = (float) $r->total;
        }
        foreach ($this->hospedagensFinalizadas()->groupByRaw('date(data_checkout_real)')->selectRaw('date(data_checkout_real) as dia, sum(valor_total) as total')->get() as $r) {
            $dias[$r->dia]['hospedagem'] = (float) $r->total;
        }
        foreach ($this->mensalidadesPagas()->groupBy('data_pagamento')->selectRaw('data_pagamento as dia, sum(valor_total) as total')->get() as $r) {
            $dias[Carbon::parse($r->dia)->toDateString()]['mensalidades'] = (float) $r->total;
        }
        ksort($dias);

        $linhas = [];
        foreach ($dias as $dia => $v) {
            $linha = ['dia' => $dia, 'pdv' => $v['pdv'] ?? 0, 'mesa' => $v['mesa'] ?? 0, 'online' => $v['online'] ?? 0, 'hospedagem' => $v['hospedagem'] ?? 0, 'mensalidades' => $v['mensalidades'] ?? 0];
            $linha['total'] = $linha['pdv'] + $linha['mesa'] + $linha['online'] + $linha['hospedagem'] + $linha['mensalidades'];
            $linhas[] = $linha;
        }

        $colunas = ['dia' => ['Dia', 'data'], 'pdv' => ['PDV', 'moeda'], 'mesa' => ['Mesas', 'moeda'], 'online' => ['Link externo', 'moeda'], 'hospedagem' => ['Hospedagem', 'moeda'], 'mensalidades' => ['Mensalidades', 'moeda'], 'total' => ['Total do dia', 'moeda']];

        // Forma de pagamento (vendas + hospedagens)
        $formas = [];
        foreach ($this->vendasPagas()->groupBy('vendas.forma_pagamento')->selectRaw('vendas.forma_pagamento as forma, sum(vendas.valor_total) as total')->get() as $r) {
            $formas[$r->forma] = ($formas[$r->forma] ?? 0) + (float) $r->total;
        }
        foreach ($this->hospedagensFinalizadas()->groupBy('forma_pagamento')->selectRaw('forma_pagamento as forma, sum(valor_total) as total')->get() as $r) {
            $formas[$r->forma] = ($formas[$r->forma] ?? 0) + (float) $r->total;
        }
        arsort($formas);

        $resumo = [['Faturamento total', $totalGeral, 'moeda']];
        foreach ($canais as $rotulo => $c) {
            $resumo[] = [$rotulo, $c['total'], 'moeda', $c['qtd'].' lançamento(s)'];
        }

        $extra = [];
        foreach ($formas as $forma => $total) {
            $extra[] = ['forma' => Financeiro::FORMAS_PAGAMENTO[$forma]['label'] ?? ($forma === 'multiplo' ? 'Múltiplas' : ucfirst((string) $forma)), 'total' => $total, 'part' => $totalGeral > 0 ? $total / $totalGeral * 100 : 0];
        }

        return [
            'resumo' => $resumo,
            'colunas' => $colunas,
            'linhas' => $linhas,
            'totais' => $this->somar($linhas, ['pdv', 'mesa', 'online', 'hospedagem', 'mensalidades', 'total']) + ['dia' => 'Total'],
            'secundaria' => [
                'titulo' => 'Por forma de pagamento (vendas e hospedagem)',
                'colunas' => ['forma' => ['Forma de pagamento', 'texto'], 'total' => ['Total', 'moeda'], 'part' => ['Participação', 'percentual']],
                'linhas' => $extra,
            ],
        ];
    }

    protected function ticketMedio(): array
    {
        $linhas = [];
        foreach ($this->vendasPagas()->groupBy('vendas.origem')->selectRaw('vendas.origem, count(*) as qtd, sum(vendas.valor_total) as total')->get() as $r) {
            $linhas[$r->origem] = ['canal' => Venda::ORIGENS[$r->origem] ?? $r->origem, 'qtd' => (int) $r->qtd, 'total' => (float) $r->total];
        }

        $pessoasMesa = (int) Atendimento::query()->where('status', 'fechado')
            ->whereBetween('fechado_em', [$this->inicio, $this->fim])
            ->when($this->unidadeId, fn ($q, $id) => $q->where('unidade_id', $id))
            ->sum('quantidade_pessoas');

        $h = $this->hospedagensFinalizadas()->selectRaw('count(*) as qtd, coalesce(sum(valor_total), 0) as total')->first();
        $linhas['hospedagem'] = ['canal' => 'Hospedagem', 'qtd' => (int) $h->qtd, 'total' => (float) $h->total];

        $linhas = array_values(array_map(fn ($l) => $l + ['ticket' => $l['qtd'] > 0 ? $l['total'] / $l['qtd'] : 0], $linhas));
        $qtd = array_sum(array_column($linhas, 'qtd'));
        $total = array_sum(array_column($linhas, 'total'));
        $mesa = collect($linhas)->firstWhere('canal', Venda::ORIGENS[Venda::ORIGEM_MESA]);

        return [
            'resumo' => [
                ['Ticket médio geral', $qtd > 0 ? $total / $qtd : 0, 'moeda'],
                ['Vendas / contas', $qtd, 'numero'],
                ['Faturamento', $total, 'moeda'],
                ['Ticket por pessoa nas mesas', $pessoasMesa > 0 && $mesa ? $mesa['total'] / $pessoasMesa : 0, 'moeda', $pessoasMesa.' pessoa(s)'],
            ],
            'colunas' => ['canal' => ['Canal', 'texto'], 'qtd' => ['Vendas / contas', 'numero'], 'total' => ['Faturamento', 'moeda'], 'ticket' => ['Ticket médio', 'moeda']],
            'linhas' => $linhas,
            'totais' => ['canal' => 'Geral', 'qtd' => $qtd, 'total' => $total, 'ticket' => $qtd > 0 ? $total / $qtd : 0],
        ];
    }

    protected function produtos(): array
    {
        $linhas = $this->vendasPagas()
            ->join('venda_itens', 'venda_itens.venda_id', '=', 'vendas.id')
            ->join('produtos', 'produtos.id', '=', 'venda_itens.produto_id')
            ->leftJoin('categorias_produtos', 'categorias_produtos.id', '=', 'produtos.categoria_id')
            ->groupBy('produtos.id', 'produtos.nome', 'produtos.sku', 'categorias_produtos.nome')
            ->selectRaw('produtos.nome as produto, produtos.sku, categorias_produtos.nome as categoria, sum(venda_itens.quantidade) as qtd, count(distinct vendas.id) as vendas, sum(venda_itens.desconto) as desconto, sum(venda_itens.subtotal) as total')
            ->orderByDesc('total')
            ->get()->map(fn ($r) => (array) $r->getAttributes())->all();

        return $this->comParticipacao($linhas, [
            'produto' => ['Produto', 'texto'], 'sku' => ['SKU', 'texto'], 'categoria' => ['Categoria', 'texto'],
            'qtd' => ['Qtd. vendida', 'numero'], 'vendas' => ['Vendas', 'numero'], 'desconto' => ['Descontos', 'moeda'],
            'total' => ['Faturamento', 'moeda'], 'part' => ['Participação', 'percentual'],
        ], 'Produtos vendidos', ['qtd', 'desconto', 'total']);
    }

    protected function tiposEntrada(): array
    {
        $linhas = $this->vendasPagas()
            ->join('venda_itens', 'venda_itens.venda_id', '=', 'vendas.id')
            ->join('tipos_entrada', 'tipos_entrada.id', '=', 'venda_itens.tipo_entrada_id')
            ->groupBy('tipos_entrada.id', 'tipos_entrada.nome')
            ->selectRaw("tipos_entrada.nome as tipo,
                sum(case when vendas.origem = 'pdv' then venda_itens.quantidade else 0 end) as qtd_pdv,
                sum(case when vendas.origem = 'online' then venda_itens.quantidade else 0 end) as qtd_online,
                sum(venda_itens.quantidade) as qtd, sum(venda_itens.subtotal) as total")
            ->orderByDesc('qtd')
            ->get()->map(fn ($r) => (array) $r->getAttributes())->all();

        return $this->comParticipacao($linhas, [
            'tipo' => ['Tipo de entrada', 'texto'], 'qtd_pdv' => ['Qtd. PDV', 'numero'], 'qtd_online' => ['Qtd. link externo', 'numero'],
            'qtd' => ['Qtd. total', 'numero'], 'total' => ['Faturamento', 'moeda'], 'part' => ['Participação', 'percentual'],
        ], 'Ingressos vendidos', ['qtd_pdv', 'qtd_online', 'qtd', 'total'], 'qtd');
    }

    protected function vendedores(): array
    {
        $linhas = $this->vendasPagas()
            ->join('users', 'users.id', '=', 'vendas.vendedor_id')
            ->groupBy('users.id', 'users.name')
            ->selectRaw("users.name as vendedor, count(*) as vendas,
                sum(case when vendas.origem = 'mesa' then 1 else 0 end) as vendas_mesa,
                sum(vendas.desconto) as desconto, sum(vendas.valor_total) as total")
            ->orderByDesc('total')
            ->get()->map(function ($r) {
                $l = (array) $r->getAttributes();
                $l['ticket'] = $l['vendas'] > 0 ? $l['total'] / $l['vendas'] : 0;

                return $l;
            })->all();

        $vendas = array_sum(array_column($linhas, 'vendas'));
        $total = array_sum(array_column($linhas, 'total'));

        return [
            'resumo' => [
                ['Vendedores com vendas', count($linhas), 'numero'],
                ['Vendas', $vendas, 'numero'],
                ['Faturamento', $total, 'moeda'],
                ['Ticket médio', $vendas > 0 ? $total / $vendas : 0, 'moeda'],
            ],
            'colunas' => [
                'vendedor' => ['Vendedor / operador', 'texto'], 'vendas' => ['Vendas', 'numero'], 'vendas_mesa' => ['Contas de mesa', 'numero'],
                'desconto' => ['Descontos', 'moeda'], 'total' => ['Faturamento', 'moeda'], 'ticket' => ['Ticket médio', 'moeda'],
            ],
            'linhas' => $linhas,
            'totais' => $this->somar($linhas, ['vendas', 'vendas_mesa', 'desconto', 'total']) + ['vendedor' => 'Total', 'ticket' => $vendas > 0 ? $total / $vendas : 0],
        ];
    }

    protected function caixas(): array
    {
        $caixas = Caixa::query()
            ->with(['terminal', 'unidade', 'usuarioAbertura'])
            ->whereBetween('data_abertura', [$this->inicio, $this->fim])
            ->when($this->unidadeId, fn ($q, $id) => $q->where('unidade_id', $id))
            ->orderBy('data_abertura')
            ->get();

        $movs = CaixaMovimentacao::query()
            ->whereIn('caixa_id', $caixas->pluck('id'))
            ->whereNull('estornado_em')
            ->groupBy('caixa_id')
            ->selectRaw("caixa_id, sum(case when tipo = 'entrada' then valor else 0 end) as entradas, sum(case when tipo = 'saida' then valor else 0 end) as saidas")
            ->get()->keyBy('caixa_id');

        $linhas = $caixas->map(function (Caixa $c) use ($movs) {
            $entradas = (float) ($movs[$c->id]->entradas ?? 0);
            $saidas = (float) ($movs[$c->id]->saidas ?? 0);

            return [
                'caixa' => '#'.$c->id,
                'terminal' => $c->terminal?->nome ?? '—',
                'unidade' => $c->unidade?->nome ?? '—',
                'operador' => $c->usuarioAbertura?->name ?? '—',
                'abertura' => $c->data_abertura,
                'fechamento' => $c->data_fechamento,
                'valor_abertura' => (float) $c->valor_abertura,
                'entradas' => $entradas,
                'saidas' => $saidas,
                'saldo' => (float) $c->valor_abertura + $entradas - $saidas,
                'diferenca' => $c->status === 'fechado' ? (float) $c->diferenca : null,
                'status' => ucfirst((string) $c->status),
            ];
        })->all();

        $entradas = array_sum(array_column($linhas, 'entradas'));
        $saidas = array_sum(array_column($linhas, 'saidas'));

        // Resumo por terminal
        $porTerminal = collect($linhas)->groupBy('terminal')->map(fn ($g, $t) => [
            'terminal' => $t, 'caixas' => $g->count(), 'entradas' => $g->sum('entradas'), 'saidas' => $g->sum('saidas'), 'diferenca' => $g->sum('diferenca'),
        ])->values()->all();

        return [
            'resumo' => [
                ['Caixas no período', count($linhas), 'numero'],
                ['Entradas', $entradas, 'moeda'],
                ['Saídas', $saidas, 'moeda'],
                ['Diferença nos fechamentos', array_sum(array_map(fn ($l) => (float) $l['diferenca'], $linhas)), 'moeda', 'informado − sistema'],
            ],
            'colunas' => [
                'caixa' => ['Caixa', 'texto'], 'terminal' => ['Terminal', 'texto'], 'unidade' => ['Unidade', 'texto'], 'operador' => ['Operador', 'texto'],
                'abertura' => ['Abertura', 'datahora'], 'fechamento' => ['Fechamento', 'datahora'], 'valor_abertura' => ['Fundo', 'moeda'],
                'entradas' => ['Entradas', 'moeda'], 'saidas' => ['Saídas', 'moeda'], 'saldo' => ['Saldo', 'moeda'],
                'diferenca' => ['Diferença', 'moeda'], 'status' => ['Situação', 'texto'],
            ],
            'linhas' => $linhas,
            'totais' => $this->somar($linhas, ['valor_abertura', 'entradas', 'saidas', 'saldo', 'diferenca']) + ['caixa' => 'Total'],
            'secundaria' => [
                'titulo' => 'Por terminal',
                'colunas' => ['terminal' => ['Terminal', 'texto'], 'caixas' => ['Caixas', 'numero'], 'entradas' => ['Entradas', 'moeda'], 'saidas' => ['Saídas', 'moeda'], 'diferenca' => ['Diferença', 'moeda']],
                'linhas' => $porTerminal,
            ],
        ];
    }

    protected function mesas(): array
    {
        $linhas = Atendimento::query()
            ->join('pontos_atendimento', 'pontos_atendimento.id', '=', 'atendimentos.ponto_atendimento_id')
            ->where('atendimentos.status', 'fechado')
            ->whereBetween('atendimentos.fechado_em', [$this->inicio, $this->fim])
            ->when($this->unidadeId, fn ($q, $id) => $q->where('atendimentos.unidade_id', $id))
            ->groupBy('pontos_atendimento.id', 'pontos_atendimento.tipo', 'pontos_atendimento.numero', 'pontos_atendimento.nome')
            ->selectRaw('pontos_atendimento.tipo, pontos_atendimento.numero, pontos_atendimento.nome,
                count(*) as contas, sum(atendimentos.quantidade_pessoas) as pessoas,
                sum(atendimentos.subtotal) as consumo, sum(atendimentos.valor_servico) as servico,
                sum(atendimentos.desconto) as desconto, sum(atendimentos.valor_total) as total,
                avg(timestampdiff(minute, atendimentos.aberto_em, atendimentos.fechado_em)) as minutos')
            ->orderBy('pontos_atendimento.tipo')->orderBy('pontos_atendimento.numero')
            ->get()->map(function ($r) {
                $l = (array) $r->getAttributes();

                return [
                    'ponto' => ucfirst($l['tipo']).' '.$l['numero'].($l['nome'] ? ' — '.$l['nome'] : ''),
                    'contas' => (int) $l['contas'],
                    'pessoas' => (int) $l['pessoas'],
                    'consumo' => (float) $l['consumo'],
                    'servico' => (float) $l['servico'],
                    'desconto' => (float) $l['desconto'],
                    'total' => (float) $l['total'],
                    'ticket' => $l['contas'] > 0 ? $l['total'] / $l['contas'] : 0,
                    'minutos' => (int) round((float) $l['minutos']),
                ];
            })->all();

        $contas = array_sum(array_column($linhas, 'contas'));
        $pessoas = array_sum(array_column($linhas, 'pessoas'));
        $total = array_sum(array_column($linhas, 'total'));

        return [
            'resumo' => [
                ['Faturamento', $total, 'moeda'],
                ['Contas fechadas', $contas, 'numero'],
                ['Ticket médio por conta', $contas > 0 ? $total / $contas : 0, 'moeda'],
                ['Ticket por pessoa', $pessoas > 0 ? $total / $pessoas : 0, 'moeda', $pessoas.' pessoa(s)'],
                ['Taxa de serviço', array_sum(array_column($linhas, 'servico')), 'moeda'],
            ],
            'colunas' => [
                'ponto' => ['Mesa / comanda', 'texto'], 'contas' => ['Contas', 'numero'], 'pessoas' => ['Pessoas', 'numero'],
                'consumo' => ['Consumo', 'moeda'], 'servico' => ['Serviço', 'moeda'], 'desconto' => ['Descontos', 'moeda'],
                'total' => ['Total', 'moeda'], 'ticket' => ['Ticket médio', 'moeda'], 'minutos' => ['Tempo médio (min)', 'numero'],
            ],
            'linhas' => $linhas,
            'totais' => $this->somar($linhas, ['contas', 'pessoas', 'consumo', 'servico', 'desconto', 'total']) + ['ponto' => 'Total', 'ticket' => $contas > 0 ? $total / $contas : 0],
        ];
    }

    protected function quartos(): array
    {
        $hospedagens = $this->hospedagensFinalizadas()->with('quarto')->get();
        $diasPeriodo = max(1, (int) $this->inicio->diffInDays($this->fim) + 1);

        $linhas = $hospedagens->groupBy('quarto_id')->map(function ($grupo) use ($diasPeriodo) {
            $quarto = $grupo->first()->quarto;
            $diarias = $grupo->sum(fn (Hospedagem $h) => Hospedagem::contarNoites($h->data_checkin_real ?? $h->data_checkin_prevista, $h->data_checkout_real));
            $total = (float) $grupo->sum('valor_total');

            return [
                'quarto' => $quarto ? 'Quarto '.$quarto->numero : '—',
                'hospedagens' => $grupo->count(),
                'hospedes' => (int) $grupo->sum('quantidade_hospedes'),
                'diarias' => $diarias,
                'desconto' => (float) $grupo->sum('desconto'),
                'total' => $total,
                'ticket' => $grupo->count() > 0 ? $total / $grupo->count() : 0,
                'diaria_media' => $diarias > 0 ? $total / $diarias : 0,
                'ocupacao' => min(100, $diarias / $diasPeriodo * 100),
            ];
        })->sortBy('quarto', SORT_NATURAL)->values()->all();

        $qtd = array_sum(array_column($linhas, 'hospedagens'));
        $diarias = array_sum(array_column($linhas, 'diarias'));
        $total = array_sum(array_column($linhas, 'total'));

        return [
            'resumo' => [
                ['Faturamento', $total, 'moeda'],
                ['Hospedagens finalizadas', $qtd, 'numero'],
                ['Diárias', $diarias, 'numero'],
                ['Ticket médio', $qtd > 0 ? $total / $qtd : 0, 'moeda'],
                ['Diária média', $diarias > 0 ? $total / $diarias : 0, 'moeda'],
            ],
            'colunas' => [
                'quarto' => ['Quarto', 'texto'], 'hospedagens' => ['Hospedagens', 'numero'], 'hospedes' => ['Hóspedes', 'numero'],
                'diarias' => ['Diárias', 'numero'], 'desconto' => ['Descontos', 'moeda'], 'total' => ['Faturamento', 'moeda'],
                'ticket' => ['Ticket médio', 'moeda'], 'diaria_media' => ['Diária média', 'moeda'], 'ocupacao' => ['Ocupação no período', 'percentual'],
            ],
            'linhas' => $linhas,
            'totais' => $this->somar($linhas, ['hospedagens', 'hospedes', 'diarias', 'desconto', 'total']) + ['quarto' => 'Total', 'ticket' => $qtd > 0 ? $total / $qtd : 0, 'diaria_media' => $diarias > 0 ? $total / $diarias : 0],
        ];
    }

    protected function linkExterno(): array
    {
        $base = Venda::query()
            ->where('vendas.origem', Venda::ORIGEM_ONLINE)
            ->whereBetween('vendas.created_at', [$this->inicio, $this->fim])
            ->when($this->unidadeId, fn ($q, $id) => $q->where('vendas.unidade_id', $id));

        $porStatus = (clone $base)->groupBy('status')->selectRaw('status, count(*) as qtd, sum(valor_total) as total')->get()->keyBy('status');

        $linhas = (clone $base)
            ->with(['itens.tipoEntrada', 'unidade'])
            ->orderByDesc('vendas.created_at')
            ->get()
            ->map(fn (Venda $v) => [
                'pedido' => '#'.$v->id,
                'data' => $v->created_at,
                'unidade' => $v->unidade?->nome ?? '—',
                'entrada' => $v->itens->map(fn ($i) => $i->tipoEntrada?->nome)->filter()->implode(', ') ?: '—',
                'qtd' => (int) $v->itens->sum('quantidade'),
                'total' => (float) $v->valor_total,
                'status' => ['pago' => 'Pago', 'pendente' => 'Aguardando pagamento', 'cancelado' => 'Cancelado'][$v->status] ?? ucfirst((string) $v->status),
            ])->all();

        $pagas = $porStatus['pago'] ?? null;
        $tentativas = (int) $porStatus->sum('qtd');

        return [
            'resumo' => [
                ['Faturamento (pagos)', (float) ($pagas->total ?? 0), 'moeda', (int) ($pagas->qtd ?? 0).' pedido(s)'],
                ['Ingressos pagos', collect($linhas)->where('status', 'Pago')->sum('qtd'), 'numero'],
                ['Ticket médio', ($pagas->qtd ?? 0) > 0 ? $pagas->total / $pagas->qtd : 0, 'moeda'],
                ['Conversão', $tentativas > 0 ? ($pagas->qtd ?? 0) / $tentativas * 100 : 0, 'percentual', 'pagos / pedidos gerados'],
                ['Aguardando / cancelados', (int) (($porStatus['pendente']->qtd ?? 0) + ($porStatus['cancelado']->qtd ?? 0)), 'numero'],
            ],
            'colunas' => [
                'pedido' => ['Pedido', 'texto'], 'data' => ['Data', 'datahora'], 'unidade' => ['Unidade', 'texto'], 'entrada' => ['Entrada', 'texto'],
                'qtd' => ['Qtd.', 'numero'], 'total' => ['Valor', 'moeda'], 'status' => ['Situação', 'texto'],
            ],
            'linhas' => $linhas,
        ];
    }

    // ---------------------------------------------------------------- utilitários

    protected function somar(array $linhas, array $chaves): array
    {
        $totais = [];
        foreach ($chaves as $chave) {
            $totais[$chave] = array_sum(array_map(fn ($l) => (float) ($l[$chave] ?? 0), $linhas));
        }

        return $totais;
    }

    /** Acrescenta a coluna de participação (%) e monta resumo/totais padrão. */
    protected function comParticipacao(array $linhas, array $colunas, string $rotuloQtd, array $somar, string $base = 'total'): array
    {
        $totalBase = array_sum(array_map(fn ($l) => (float) $l[$base], $linhas));
        $linhas = array_map(fn ($l) => $l + ['part' => $totalBase > 0 ? (float) $l[$base] / $totalBase * 100 : 0], $linhas);
        $totais = $this->somar($linhas, $somar);

        return [
            'resumo' => [
                ['Faturamento', $totais['total'] ?? 0, 'moeda'],
                [$rotuloQtd, $totais['qtd'] ?? 0, 'numero'],
                ['Itens diferentes', count($linhas), 'numero'],
                ['Mais vendido', $linhas[0][array_key_first($colunas)] ?? '—', 'texto'],
            ],
            'colunas' => $colunas,
            'linhas' => $linhas,
            'totais' => $totais + [array_key_first($colunas) => 'Total', 'part' => $linhas ? 100 : 0],
        ];
    }
}
