@extends('layouts.app')

@section('titulo', 'Caixa')

@section('conteudo')
    @can('abrir', \App\Models\Caixa::class)
        <form method="POST" action="{{ route('caixas.abrir') }}" class="mb-6 flex flex-wrap items-end gap-3 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            @csrf
            <div>
                <label class="block text-xs font-medium text-slate-500">Terminal</label>
                <select name="terminal_id" required class="mt-1 min-w-56 rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    <option value="">Selecione o terminal...</option>
                    @foreach ($terminais as $terminal)
                        <option value="{{ $terminal->id }}" @disabled(in_array($terminal->id, $terminaisComCaixaAbertoIds))>
                            {{ $terminal->nome }} ({{ $terminal->unidade->nome }}){{ in_array($terminal->id, $terminaisComCaixaAbertoIds) ? ' — já aberto' : '' }}
                        </option>
                    @endforeach
                </select>
                @if ($terminais->isEmpty())
                    <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">Nenhum terminal cadastrado. <a href="{{ route('terminais.create') }}" class="underline">Crie um terminal</a> antes de abrir o caixa.</p>
                @endif
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">Valor de abertura (R$)</label>
                <input type="number" step="0.01" min="0" name="valor_abertura" required class="mt-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <button class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600">Abrir caixa</button>
        </form>
    @endcan

    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/[0.03] dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">Terminal</th>
                    <th class="px-4 py-3">Unidade</th>
                    <th class="px-4 py-3">Abertura</th>
                    <th class="px-4 py-3">Responsável</th>
                    <th class="px-4 py-3">Valor abertura</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($caixas as $caixa)
                    <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $caixa->terminal->nome ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $caixa->unidade->nome }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $caixa->data_abertura->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $caixa->usuarioAbertura->name }}</td>
                        <td class="px-4 py-3 text-slate-500">R$ {{ number_format($caixa->valor_abertura, 2, ',', '.') }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$caixa->status" /></td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('caixas.show', $caixa) }}" class="text-sky-700 hover:underline">Ver</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">Nenhum caixa registrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $caixas->links() }}</div>
@endsection
