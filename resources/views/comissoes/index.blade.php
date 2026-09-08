@extends('layouts.app')

@section('titulo', 'Comissões')

@section('conteudo')
    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/[0.03] dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">Vendedor</th>
                    <th class="px-4 py-3">Origem</th>
                    <th class="px-4 py-3">Base de cálculo</th>
                    <th class="px-4 py-3">%</th>
                    <th class="px-4 py-3">Valor</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($comissoes as $comissao)
                    <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $comissao->vendedor->name }}</td>
                        <td class="px-4 py-3 text-slate-500">
                            @if ($comissao->tipo === 'reativacao')
                                <span class="inline-flex items-center gap-1 rounded-full bg-violet-50 px-2 py-0.5 text-xs font-medium text-violet-700 dark:bg-violet-500/15 dark:text-violet-400">Reativação</span>
                                {{ $comissao->contrato?->numero_contrato }}
                            @elseif ($comissao->venda_id)
                                Venda #{{ $comissao->venda_id }}
                            @else
                                Contrato {{ $comissao->contrato?->numero_contrato }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-500">R$ {{ number_format($comissao->base_calculo, 2, ',', '.') }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $comissao->percentual }}%</td>
                        <td class="px-4 py-3 font-medium">R$ {{ number_format($comissao->valor, 2, ',', '.') }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$comissao->status" /></td>
                        <td class="px-4 py-3 text-right">
                            @if ($comissao->status === 'pendente')
                                @can('pagar', $comissao)
                                    <form method="POST" action="{{ route('comissoes.pagar', $comissao) }}">
                                        @csrf
                                        <button class="text-sky-700 hover:underline">Marcar como paga</button>
                                    </form>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">Nenhuma comissão registrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $comissoes->links() }}</div>
@endsection
