@extends('layouts.app')

@section('titulo', 'Formas de Pagamento')

@section('conteudo')
    <div class="mb-6 flex items-center justify-between gap-3">
        <p class="text-sm text-slate-500 dark:text-slate-400">Formas oferecidas no PDV e no fechamento de mesas e comandas. Pix integrado gera o QR Code no banco e só conclui quando o pagamento é confirmado.</p>
        @can('create', App\Models\FormaPagamento::class)
            <a href="{{ route('formas-pagamento.create') }}" class="shrink-0 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600">+ Nova forma</a>
        @endcan
    </div>

    @unless($gatewayPixAtivo)
        <x-alert type="info" class="mb-4">
            Nenhum gateway Pix configurado. Para usar Pix integrado (QR Code), configure o Itaú ou o Mercado Pago em
            <a href="{{ route('empresa.edit') }}" class="font-semibold underline">Minha Empresa</a>.
        </x-alert>
    @endunless

    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/[0.03] dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">Ordem</th>
                    <th class="px-4 py-3">Nome</th>
                    <th class="px-4 py-3">Tipo</th>
                    <th class="px-4 py-3">Integração</th>
                    <th class="px-4 py-3">Situação</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($formas as $forma)
                    <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $forma->ordem }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ $forma->nome }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $forma->tipo_label }}</td>
                        <td class="px-4 py-3">
                            @if($forma->tipo !== 'pix')
                                <span class="text-slate-400">—</span>
                            @elseif($forma->integrado)
                                <x-badge variant="success">QR Code integrado</x-badge>
                            @else
                                <x-badge>Manual</x-badge>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($forma->ativo)
                                <x-badge variant="success">Ativa</x-badge>
                            @else
                                <x-badge>Inativa</x-badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @can('update', $forma)
                                <a href="{{ route('formas-pagamento.edit', $forma) }}" class="text-sky-700 hover:underline dark:text-sky-400">Editar</a>
                            @endcan
                            @can('delete', $forma)
                                <form method="POST" action="{{ route('formas-pagamento.destroy', $forma) }}" class="ml-2 inline" onsubmit="return confirm('Excluir esta forma de pagamento?')">
                                    @csrf @method('DELETE')
                                    <button class="text-rose-600 hover:underline">Excluir</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Nenhuma forma de pagamento cadastrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
