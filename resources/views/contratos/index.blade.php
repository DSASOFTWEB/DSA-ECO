@extends('layouts.app')

@section('titulo', 'Contratos')

@section('conteudo')
    <div class="mb-6 flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:flex-row sm:items-center sm:justify-between">
        <form method="GET" class="flex flex-wrap gap-3">
            <select name="status" class="h-11 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                <option value="">Todos os status</option>
                @foreach (['ativo', 'suspenso', 'cancelado', 'encerrado'] as $opcao)
                    <option value="{{ $opcao }}" @selected(request('status') === $opcao)>{{ ucfirst($opcao) }}</option>
                @endforeach
            </select>
            <button class="h-11 rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">Filtrar</button>
        </form>

        @can('create', \App\Models\Contrato::class)
            <a href="{{ route('contratos.create') }}" class="inline-flex h-11 items-center rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">+ Novo contrato</a>
        @endcan
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="border-b border-gray-100 bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-white/[0.02] dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">Nº</th>
                    <th class="px-4 py-3">Cliente</th>
                    <th class="px-4 py-3">Plano</th>
                    <th class="px-4 py-3">Valor mensal</th>
                    <th class="px-4 py-3">Vencimento</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @forelse ($contratos as $contrato)
                    <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                        <td class="px-4 py-3 text-slate-500">{{ $contrato->numero_contrato }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $contrato->cliente->nome }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $contrato->plano->nome }}</td>
                        <td class="px-4 py-3 text-slate-500">R$ {{ number_format($contrato->valor_mensal, 2, ',', '.') }}</td>
                        <td class="px-4 py-3 text-slate-500">dia {{ $contrato->dia_vencimento }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$contrato->status" /></td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('contratos.show', $contrato) }}" class="text-sky-700 hover:underline">Ver</a>
                            @if ($contrato->estaAtivo())
                                @can('update', $contrato)
                                    <a href="{{ route('contratos.edit', $contrato) }}" class="ml-3 text-indigo-700 hover:underline">Editar</a>
                                    <a href="{{ route('contratos.prorrogar-form', $contrato) }}" class="ml-3 text-amber-700 hover:underline">Prorrogar</a>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">Nenhum contrato encontrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $contratos->links() }}</div>
@endsection
