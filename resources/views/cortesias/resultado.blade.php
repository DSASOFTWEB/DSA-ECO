@extends('layouts.app')

@section('titulo', 'Cortesias geradas')

@section('conteudo')
    <div class="max-w-xl">
        <a href="{{ route('cortesias.index') }}" class="mb-4 inline-block text-sm text-brand-600 hover:underline">&larr; Gerar mais cortesias</a>

        <div class="rounded-2xl border border-violet-300 bg-violet-100 p-6 shadow-sm dark:border-violet-700 dark:bg-violet-500/[0.1]">
            <h1 class="text-lg font-bold text-violet-800 dark:text-violet-300">{{ $acessos->count() }} entrada(s) de cortesia gerada(s)</h1>
            <p class="mt-1 text-sm text-violet-700 dark:text-violet-400">Cada uma tem seu próprio QR — validado na portaria como qualquer voucher.</p>

            <a href="{{ route('cortesias.voucher', ['acessos' => $acessos->pluck('id')->implode(',')]) }}" target="_blank"
               class="mt-5 block w-full rounded-xl bg-violet-600 py-3.5 text-center text-base font-semibold text-white shadow-sm transition hover:bg-violet-700">
                Baixar voucher(s) em PDF
            </a>
        </div>

        <div class="mt-5 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:bg-white/[0.02] dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Código</th>
                        <th class="px-4 py-3">Para</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Válido até</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @foreach ($acessos as $acesso)
                        <tr>
                            <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $acesso->codigo_validacao }}</td>
                            <td class="px-4 py-3 text-gray-800 dark:text-white/90">{{ $acesso->nomeTitular() ?? 'Convidado' }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $acesso->tipoEntrada?->nome ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $acesso->validade_ate?->format('d/m/Y') ?? 'Sem prazo' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
