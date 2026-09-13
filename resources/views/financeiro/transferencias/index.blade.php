@extends('layouts.app')

@section('titulo', 'Transferências entre Caixas')

@section('conteudo')
    @can('create', App\Models\TransferenciaCaixa::class)
        <div class="mb-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Nova transferência</h2>
            @if ($caixasAbertos->count() < 2)
                <p class="text-sm text-slate-400">É preciso ter pelo menos 2 caixas abertos para transferir valor entre eles.</p>
            @else
                <form method="POST" action="{{ route('transferencias.store') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-4" onsubmit="return confirm('Confirmar esta transferência entre caixas?')">
                    @csrf
                    <select name="caixa_origem_id" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700">
                        <option value="">De (caixa origem)</option>
                        @foreach ($caixasAbertos as $caixa)
                            <option value="{{ $caixa->id }}">{{ $caixa->terminal->nome ?? $caixa->unidade->nome }}</option>
                        @endforeach
                    </select>
                    <select name="caixa_destino_id" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700">
                        <option value="">Para (caixa destino)</option>
                        @foreach ($caixasAbertos as $caixa)
                            <option value="{{ $caixa->id }}">{{ $caixa->terminal->nome ?? $caixa->unidade->nome }}</option>
                        @endforeach
                    </select>
                    <input type="number" step="0.01" name="valor" placeholder="Valor (R$)" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700">
                    <input type="text" name="observacao" placeholder="Observação (opcional)" class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700">
                    <button class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700 sm:col-span-4 sm:w-fit">Transferir</button>
                </form>
            @endif
        </div>
    @endcan

    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:bg-white/[0.02] dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">Data</th>
                    <th class="px-4 py-3">De</th>
                    <th class="px-4 py-3">Para</th>
                    <th class="px-4 py-3">Valor</th>
                    <th class="px-4 py-3">Usuário</th>
                    <th class="px-4 py-3">Observação</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @forelse ($transferencias as $transferencia)
                    <tr>
                        <td class="px-4 py-3 whitespace-nowrap text-slate-500">{{ $transferencia->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $transferencia->caixaOrigem->terminal->nome ?? $transferencia->caixaOrigem->unidade->nome }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $transferencia->caixaDestino->terminal->nome ?? $transferencia->caixaDestino->unidade->nome }}</td>
                        <td class="px-4 py-3 font-medium">R$ {{ number_format($transferencia->valor, 2, ',', '.') }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $transferencia->usuario->name }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $transferencia->observacao ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Nenhuma transferência realizada ainda.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $transferencias->links() }}</div>
@endsection
