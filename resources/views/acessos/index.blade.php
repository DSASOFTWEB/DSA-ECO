@extends('layouts.app')

@section('titulo', 'Controle de acesso')

@section('conteudo')
    <form method="GET" class="mb-6 flex flex-wrap gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
        <select name="autorizado" class="h-11 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
            <option value="">Todos</option>
            <option value="1" @selected(request('autorizado') === '1')>Autorizados</option>
            <option value="0" @selected(request('autorizado') === '0')>Negados</option>
        </select>
        <button class="h-11 rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">Filtrar</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="border-b border-gray-100 bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-white/[0.02] dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">Data/hora</th>
                    <th class="px-4 py-3">Titular</th>
                    <th class="px-4 py-3">Origem</th>
                    <th class="px-4 py-3">Unidade</th>
                    <th class="px-4 py-3">Tipo</th>
                    <th class="px-4 py-3">Resultado</th>
                    <th class="px-4 py-3">Validação</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @forelse ($acessos as $acesso)
                    <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                        <td class="px-4 py-3 text-slate-500">{{ $acesso->registrado_em->format('d/m/Y H:i:s') }}</td>
                        <td class="px-4 py-3 font-medium text-gray-800 dark:text-white/90">
                            {{ $acesso->nomeTitular() ?? 'Código inválido' }}
                        </td>
                        <td class="px-4 py-3 text-slate-500">
                            @php
                                $origemLabel = [
                                    'catraca' => 'Catraca/QR',
                                    'pdv_avulsa' => 'PDV — avulsa',
                                    'pdv_plano' => 'PDV — check-in',
                                    'online' => 'Link — venda avulsa',
                                    'checkin_online' => 'Link — check-in',
                                    'cortesia' => 'Cortesia',
                                ][$acesso->origem] ?? ucfirst($acesso->origem);
                            @endphp
                            {{ $origemLabel }}
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $acesso->unidade->nome }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ ucfirst($acesso->tipo) }}</td>
                        <td class="px-4 py-3">
                            @if ($acesso->autorizado)
                                <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400">Autorizado</span>
                            @else
                                <span class="inline-flex rounded-full bg-rose-50 px-2.5 py-1 text-xs font-medium text-rose-700 dark:bg-rose-500/15 dark:text-rose-400">{{ $acesso->motivo_negado }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if (! $acesso->precisaValidacao())
                                <span class="text-xs text-slate-300">—</span>
                            @elseif ($acesso->jaValidado())
                                <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400">Validado {{ $acesso->validado_em->format('d/m H:i') }}</span>
                            @else
                                <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700 dark:bg-amber-500/15 dark:text-amber-400">Aguardando portaria</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">Nenhum acesso registrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $acessos->links() }}</div>
@endsection
