{{-- Tela genérica dos relatórios gerenciais (estrutura em RelatorioGerencialService). --}}
@extends($imprimir ? 'layouts.impressao' : 'layouts.app')

@section('titulo', $relatorio['titulo'])

@php
    $fmt = function ($valor, string $formato) {
        if ($valor === null || $valor === '') {
            return '—';
        }

        return match ($formato) {
            'moeda' => 'R$ '.number_format((float) $valor, 2, ',', '.'),
            'numero' => number_format((float) $valor, (float) $valor == (int) $valor ? 0 : 2, ',', '.'),
            'percentual' => number_format((float) $valor, 1, ',', '.').'%',
            'data' => \Illuminate\Support\Carbon::parse($valor)->format('d/m/Y'),
            'datahora' => \Illuminate\Support\Carbon::parse($valor)->format('d/m/Y H:i'),
            default => (string) $valor,
        };
    };
    $alinhaDireita = fn (string $formato) => in_array($formato, ['moeda', 'numero', 'percentual'], true);
    $filtros = array_filter(['inicio' => $inicio->toDateString(), 'fim' => $fim->toDateString(), 'unidade_id' => $unidadeId]);
    $nomeUnidade = $unidadeId ? $unidades->firstWhere('id', $unidadeId)?->nome : 'Todas as unidades';
    $atalhos = [
        'Hoje' => [now(), now()],
        'Ontem' => [now()->subDay(), now()->subDay()],
        '7 dias' => [now()->subDays(6), now()],
        'Este mês' => [now()->startOfMonth(), now()],
        'Mês passado' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
    ];
@endphp

@section('conteudo')
    @if ($imprimir)
        <div class="mb-4 border-b border-slate-300 pb-3">
            <h1 class="text-xl font-bold">{{ $relatorio['titulo'] }}</h1>
            <p class="text-sm text-slate-600">{{ $inicio->format('d/m/Y') }} a {{ $fim->format('d/m/Y') }} · {{ $nomeUnidade }} · gerado em {{ now()->format('d/m/Y H:i') }}</p>
        </div>
    @else
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <a href="{{ route('relatorios.index') }}" class="text-xs font-medium text-brand-600 hover:underline">← Relatórios</a>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $relatorio['descricao'] }}</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('relatorios.gerencial.excel', ['relatorio' => $chave] + $filtros) }}" class="inline-flex h-10 items-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">⬇ Excel</a>
                <a href="{{ route('relatorios.gerencial', ['relatorio' => $chave, 'imprimir' => 1] + $filtros) }}" target="_blank" class="inline-flex h-10 items-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">🖨 Imprimir / PDF</a>
            </div>
        </div>

        {{-- Troca rápida de relatório mantendo o filtro --}}
        <nav class="mb-4 flex gap-1.5 overflow-x-auto pb-1">
            @foreach (\App\Services\Relatorios\RelatorioGerencialService::RELATORIOS as $chaveRel => $rel)
                <a href="{{ route('relatorios.gerencial', ['relatorio' => $chaveRel] + $filtros) }}"
                   @class([
                       'shrink-0 rounded-full px-3 py-1.5 text-xs font-semibold transition',
                       'bg-brand-500 text-white' => $chaveRel === $chave,
                       'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10' => $chaveRel !== $chave,
                   ])>{{ $rel['titulo'] }}</a>
            @endforeach
        </nav>

        <form method="GET" class="mb-5 flex flex-wrap items-end gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">De</label>
                <input type="date" name="inicio" value="{{ $inicio->toDateString() }}" class="mt-1 h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">Até</label>
                <input type="date" name="fim" value="{{ $fim->toDateString() }}" class="mt-1 h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">Unidade</label>
                <select name="unidade_id" class="mt-1 h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                    <option value="">Todas</option>
                    @foreach ($unidades as $unidade)
                        <option value="{{ $unidade->id }}" @selected($unidadeId === $unidade->id)>{{ $unidade->nome }}</option>
                    @endforeach
                </select>
            </div>
            <button class="h-10 rounded-lg bg-brand-500 px-5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">Aplicar</button>
            <div class="flex flex-wrap gap-1.5 sm:ml-auto">
                @foreach ($atalhos as $rotulo => [$de, $ate])
                    <a href="{{ route('relatorios.gerencial', array_filter(['relatorio' => $chave, 'inicio' => $de->toDateString(), 'fim' => $ate->toDateString(), 'unidade_id' => $unidadeId])) }}"
                       class="rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">{{ $rotulo }}</a>
                @endforeach
            </div>
        </form>
    @endif

    {{-- Cards de resumo --}}
    <div @class(['mb-5 grid grid-cols-2 gap-3 md:grid-cols-3', 'xl:grid-cols-4' => count($relatorio['resumo']) === 4, 'xl:grid-cols-5' => count($relatorio['resumo']) >= 5])>
        @foreach ($relatorio['resumo'] as $card)
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $card[0] }}</p>
                <p class="mt-1 truncate text-xl font-bold text-gray-900 dark:text-white" title="{{ $fmt($card[1], $card[2]) }}">{{ $fmt($card[1], $card[2]) }}</p>
                @isset($card[3])<p class="mt-0.5 text-xs text-gray-400">{{ $card[3] }}</p>@endisset
            </div>
        @endforeach
    </div>

    @foreach (array_filter([$relatorio, $relatorio['secundaria'] ?? null]) as $tabela)
        @if ($loop->index === 1)
            <h3 class="mb-2 mt-6 text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $tabela['titulo'] }}</h3>
        @endif
        <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/[0.03] dark:text-gray-400">
                    <tr>
                        @foreach ($tabela['colunas'] as [$rotulo, $formato])
                            <th @class(['whitespace-nowrap px-4 py-3', 'text-right' => $alinhaDireita($formato), 'text-left' => ! $alinhaDireita($formato)])>{{ $rotulo }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($tabela['linhas'] as $linha)
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                            @foreach ($tabela['colunas'] as $chaveCol => [$rotulo, $formato])
                                <td @class(['whitespace-nowrap px-4 py-2.5 text-gray-700 dark:text-gray-300', 'text-right tabular-nums' => $alinhaDireita($formato), 'text-rose-600' => $chaveCol === 'diferenca' && (float) ($linha[$chaveCol] ?? 0) < 0])>{{ $fmt($linha[$chaveCol] ?? null, $formato) }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($tabela['colunas']) }}" class="px-4 py-10 text-center text-gray-400">Sem dados no período.</td></tr>
                    @endforelse
                </tbody>
                @if (! empty($tabela['totais']) && count($tabela['linhas']))
                    <tfoot class="border-t-2 border-gray-200 bg-gray-50 font-semibold text-gray-900 dark:border-gray-700 dark:bg-white/[0.03] dark:text-white">
                        <tr>
                            @foreach ($tabela['colunas'] as $chaveCol => [$rotulo, $formato])
                                <td @class(['whitespace-nowrap px-4 py-3', 'text-right tabular-nums' => $alinhaDireita($formato)])>{{ array_key_exists($chaveCol, $tabela['totais']) ? $fmt($tabela['totais'][$chaveCol], is_string($tabela['totais'][$chaveCol]) ? 'texto' : $formato) : '' }}</td>
                            @endforeach
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    @endforeach

    @unless ($imprimir)
        <p class="mt-3 text-xs text-gray-400">{{ $inicio->format('d/m/Y') }} a {{ $fim->format('d/m/Y') }} · {{ $nomeUnidade }}</p>
    @endunless
@endsection
