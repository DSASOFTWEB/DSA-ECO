@extends('layouts.app')

@section('titulo', 'Carteirinha '.$carteirinha->codigo)

@section('conteudo')
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 text-center shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <x-carteirinha-card :carteirinha="$carteirinha" />

            <a href="{{ route('carteirinhas.imprimir', $carteirinha) }}" target="_blank" class="mt-4 inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-4 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M6 9V4h12v5M6 18h12v4H6v-4Zm-2-9h16a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1h-3v-3H6v3H3a1 1 0 0 1-1-1v-6a1 1 0 0 1 1-1Z"/></svg>
                Imprimir (PDF)
            </a>

            <div class="mt-4 flex justify-center gap-2">
                @if ($carteirinha->estaAtiva())
                    @can('bloquear', $carteirinha)
                        <form method="POST" action="{{ route('carteirinhas.bloquear', $carteirinha) }}" class="flex gap-1">
                            @csrf
                            <input type="text" name="motivo_bloqueio" placeholder="Motivo" required class="h-9 w-36 rounded-lg border border-gray-300 bg-transparent px-3 text-xs text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
                            <button class="rounded-lg bg-rose-600 px-3 py-2 text-xs font-semibold text-white shadow-theme-xs hover:bg-rose-700">Bloquear</button>
                        </form>
                    @endcan
                @else
                    @can('bloquear', $carteirinha)
                        <form method="POST" action="{{ route('carteirinhas.desbloquear', $carteirinha) }}">
                            @csrf
                            <button class="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-theme-xs hover:bg-emerald-700">Desbloquear</button>
                        </form>
                    @endcan
                @endif
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-6 lg:col-span-2">
            <h3 class="mb-5 text-lg font-semibold text-gray-800 dark:text-white/90">Últimos acessos</h3>
            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-slate-50">
                    @forelse ($carteirinha->acessos as $acesso)
                        <tr>
                            <td class="py-2">{{ $acesso->registrado_em->format('d/m/Y H:i') }}</td>
                            <td class="py-2 text-slate-500">{{ ucfirst($acesso->tipo) }}</td>
                            <td class="py-2">
                                @if ($acesso->autorizado)
                                    <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400">Autorizado</span>
                                @else
                                    <span class="inline-flex rounded-full bg-rose-50 px-2.5 py-1 text-xs font-medium text-rose-700 dark:bg-rose-500/15 dark:text-rose-400">Negado: {{ $acesso->motivo_negado }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td class="py-6 text-center text-slate-400">Nenhum acesso registrado ainda.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
