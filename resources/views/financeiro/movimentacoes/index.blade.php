@extends('layouts.app')

@section('titulo', 'Movimentações')

@section('conteudo')
    <form method="GET" action="{{ route('movimentacoes.index') }}" class="mb-6 grid grid-cols-2 gap-3 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:grid-cols-4 lg:grid-cols-8">
        <input type="date" name="data_inicial" value="{{ request('data_inicial') }}" class="col-span-1 rounded-lg border border-slate-300 px-2 py-2 text-xs dark:border-gray-700">
        <input type="date" name="data_final" value="{{ request('data_final') }}" class="col-span-1 rounded-lg border border-slate-300 px-2 py-2 text-xs dark:border-gray-700">
        <select name="caixa_id" class="col-span-1 rounded-lg border border-slate-300 px-2 py-2 text-xs dark:border-gray-700">
            <option value="">Caixa/terminal</option>
            @foreach ($caixas as $caixa)
                <option value="{{ $caixa->id }}" @selected(request('caixa_id') == $caixa->id)>{{ $caixa->terminal->nome ?? ('Caixa #'.$caixa->id) }} — {{ $caixa->data_abertura->format('d/m/Y') }}@if($caixa->estaAberto()) (aberto)@endif</option>
            @endforeach
        </select>
        <select name="tipo" class="col-span-1 rounded-lg border border-slate-300 px-2 py-2 text-xs dark:border-gray-700">
            <option value="">Tipo</option>
            <option value="entrada" @selected(request('tipo') === 'entrada')>Entrada</option>
            <option value="saida" @selected(request('tipo') === 'saida')>Saída</option>
        </select>
        <select name="categoria" class="col-span-1 rounded-lg border border-slate-300 px-2 py-2 text-xs dark:border-gray-700">
            <option value="">Categoria</option>
            @foreach ($categoriasEntrada + $categoriasSaida as $chave => $label)
                <option value="{{ $chave }}" @selected(request('categoria') === $chave)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="forma_pagamento" class="col-span-1 rounded-lg border border-slate-300 px-2 py-2 text-xs dark:border-gray-700">
            <option value="">Forma de pagamento</option>
            @foreach ($formasPagamento as $chave => $forma)
                <option value="{{ $chave }}" @selected(request('forma_pagamento') === $chave)>{{ $forma['label'] }}</option>
            @endforeach
        </select>
        <select name="usuario_id" class="col-span-1 rounded-lg border border-slate-300 px-2 py-2 text-xs dark:border-gray-700">
            <option value="">Usuário</option>
            @foreach ($usuarios as $usuario)
                <option value="{{ $usuario->id }}" @selected(request('usuario_id') == $usuario->id)>{{ $usuario->name }}</option>
            @endforeach
        </select>
        <div class="col-span-2 flex flex-wrap gap-2 sm:col-span-4 lg:col-span-2">
            <button class="rounded-lg bg-sky-600 px-4 py-2 text-xs font-semibold text-white hover:bg-sky-700">Filtrar</button>
            <a href="{{ route('movimentacoes.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-gray-700 dark:text-slate-300">Limpar</a>
            <a href="{{ route('movimentacoes.imprimir', request()->query()) }}" target="_blank" class="rounded-lg bg-slate-700 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">🖨 Imprimir</a>
        </div>
    </form>

    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:bg-white/[0.02] dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">Data</th>
                    <th class="px-4 py-3">Caixa/terminal</th>
                    <th class="px-4 py-3">Categoria</th>
                    <th class="px-4 py-3">Descrição</th>
                    <th class="px-4 py-3">Forma</th>
                    <th class="px-4 py-3">Usuário</th>
                    <th class="px-4 py-3 text-right">Valor</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @forelse ($movimentacoes as $mov)
                    <tr x-data="{ editando: false }">
                        <td class="px-4 py-3 whitespace-nowrap text-slate-500">{{ $mov->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-slate-500">{{ $mov->caixa->terminal->nome ?? $mov->caixa->unidade->nome }}</td>
                        <td class="px-4 py-3">
                            <span x-show="! editando">{{ \App\Support\Financeiro::labelCategoria($mov->tipo, $mov->categoria) }}</span>
                            <select x-show="editando" x-cloak form="form-editar-{{ $mov->id }}" name="categoria" class="rounded-lg border border-slate-300 px-2 py-1 text-xs dark:border-gray-700">
                                @foreach (($mov->tipo === 'entrada' ? $categoriasEntrada : $categoriasSaida) as $chave => $label)
                                    <option value="{{ $chave }}" @selected($mov->categoria === $chave)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="px-4 py-3 text-slate-500">
                            <span x-show="! editando">{{ $mov->descricao }}</span>
                            <input x-show="editando" x-cloak form="form-editar-{{ $mov->id }}" type="text" name="descricao" value="{{ $mov->descricao }}" class="rounded-lg border border-slate-300 px-2 py-1 text-xs dark:border-gray-700">
                        </td>
                        <td class="px-4 py-3 text-slate-500">
                            <span x-show="! editando">{{ \App\Support\Financeiro::labelFormaPagamento($mov->forma_pagamento) ?? '—' }}</span>
                            <select x-show="editando" x-cloak form="form-editar-{{ $mov->id }}" name="forma_pagamento" class="rounded-lg border border-slate-300 px-2 py-1 text-xs dark:border-gray-700">
                                <option value="">—</option>
                                @foreach ($formasPagamento as $chave => $forma)
                                    <option value="{{ $chave }}" @selected($mov->forma_pagamento === $chave)>{{ $forma['label'] }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $mov->usuario->name }}</td>
                        <td class="px-4 py-3 text-right font-medium {{ $mov->tipo === 'saida' ? 'text-rose-600' : 'text-emerald-600' }}">
                            {{ $mov->tipo === 'saida' ? '-' : '+' }} R$ {{ number_format($mov->valor, 2, ',', '.') }}
                        </td>
                        <td class="px-4 py-3">
                            @if ($mov->estaEstornada())
                                <span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-700 dark:bg-amber-500/15 dark:text-amber-400">Estornada</span>
                            @elseif (str_starts_with($mov->categoria, 'transferencia'))
                                <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600 dark:bg-white/5 dark:text-slate-300">Transferência</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if ($mov->podeEditar())
                                @can('update', $mov)
                                    <form id="form-editar-{{ $mov->id }}" method="POST" action="{{ route('movimentacoes.update', $mov) }}" class="inline">@csrf @method('PUT')</form>
                                    <button type="button" @click="editando = ! editando" x-text="editando ? 'Cancelar' : 'Editar'" class="text-xs text-sky-600 hover:underline"></button>
                                    <button type="submit" form="form-editar-{{ $mov->id }}" x-show="editando" x-cloak class="ml-2 text-xs font-semibold text-emerald-600 hover:underline">Salvar</button>
                                @endcan
                            @endif
                            @if ($mov->podeEstornar())
                                @can('estornar', $mov)
                                    <form method="POST" action="{{ route('movimentacoes.estornar', $mov) }}" class="mt-1 inline" onsubmit="return confirm('Estornar este lançamento? Será criado um lançamento reverso.')">
                                        @csrf
                                        <button class="text-xs text-rose-600 hover:underline">Estornar</button>
                                    </form>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-8 text-center text-slate-400">Nenhuma movimentação encontrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $movimentacoes->links() }}</div>
@endsection
