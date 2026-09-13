@extends('layouts.app')

@section('titulo', 'Estoque — Quarto '.$quarto->numero)

@section('conteudo')
    <div class="mb-6">
        <a href="{{ route('quartos.estoque-geral') }}" class="text-sm text-brand-600 hover:underline">&larr; Voltar</a>
    </div>

    <h2 class="mb-6 text-2xl font-bold tracking-tight text-gray-800 dark:text-white/90">Quarto {{ $quarto->numero }} <span class="text-base font-normal text-slate-400">— {{ $quarto->unidade->nome }}</span></h2>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
            <h3 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Itens emprestados agora</h3>
            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-slate-50 dark:divide-gray-800">
                    @forelse ($itens as $item)
                        <tr>
                            <td class="py-2 text-slate-700 dark:text-slate-200">{{ $item->produto->nome }}</td>
                            <td class="py-2 text-right font-semibold">{{ $item->quantidade }}</td>
                            <td class="py-2 pl-3 text-right">
                                @can('comodato', $quarto)
                                    <form method="POST" action="{{ route('quartos.estoque.devolver', [$quarto, $item->produto]) }}" class="inline-flex items-center gap-1" onsubmit="return confirm('Devolver este item ao estoque geral?')">
                                        @csrf
                                        <input type="number" name="quantidade" value="{{ $item->quantidade }}" min="1" max="{{ $item->quantidade }}" class="w-16 rounded-lg border border-slate-300 px-2 py-1 text-xs dark:border-gray-700 dark:bg-gray-800">
                                        <button class="rounded-lg bg-slate-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-slate-700">Devolver</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-6 text-center text-slate-400">Nenhum item emprestado neste quarto.</td></tr>
                    @endforelse
                </tbody>
            </table>

            @can('comodato', $quarto)
                <form method="POST" action="{{ route('quartos.estoque.emprestar', $quarto) }}" class="mt-4 flex flex-wrap items-end gap-2 border-t border-gray-100 pt-4 dark:border-gray-800">
                    @csrf
                    <div class="flex-1">
                        <label class="block text-xs font-medium text-slate-500">Produto do estoque</label>
                        <select name="produto_id" required class="mt-1 w-full rounded-lg border border-slate-300 px-2 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                            <option value="">Selecione...</option>
                            @foreach ($produtos as $produto)
                                <option value="{{ $produto->id }}">{{ $produto->nome }} @if($produto->controla_estoque) (disp.: {{ $produto->estoque_atual }}) @endif</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500">Qtd.</label>
                        <input type="number" name="quantidade" min="1" value="1" required class="mt-1 w-20 rounded-lg border border-slate-300 px-2 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    </div>
                    <button class="h-10 rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white hover:bg-brand-600">Emprestar</button>
                </form>
            @endcan
        </div>

        <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-6 lg:col-span-2">
            <h3 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Histórico de comodato</h3>
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="py-2">Data</th>
                        <th class="py-2">Produto</th>
                        <th class="py-2">Tipo</th>
                        <th class="py-2">Usuário</th>
                        <th class="py-2 text-right">Qtd.</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50 dark:divide-gray-800">
                    @forelse ($historico as $mov)
                        <tr>
                            <td class="py-2 whitespace-nowrap text-slate-500">{{ $mov->created_at->format('d/m/Y H:i') }}</td>
                            <td class="py-2 text-slate-700 dark:text-slate-200">{{ $mov->produto->nome }}</td>
                            <td class="py-2">
                                @if ($mov->tipo === 'comodato')
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-500/15 dark:text-amber-400">Emprestado</span>
                                @else
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400">Devolvido</span>
                                @endif
                            </td>
                            <td class="py-2 text-slate-500">{{ $mov->usuario->name }}</td>
                            <td class="py-2 text-right font-medium">{{ $mov->quantidade }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-slate-400">Nenhuma movimentação ainda.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
