@extends('layouts.app')

@section('titulo', 'Hospedagem — Quarto '.$hospedagem->quarto->numero)

@section('conteudo')
    <div class="mb-4">
        <a href="{{ url()->previous(route('hospedagens.index')) }}" class="text-sm text-brand-600 hover:underline">&larr; Voltar</a>
    </div>

    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-800 dark:text-white/90">Quarto {{ $hospedagem->quarto->numero }} — {{ $hospedagem->cliente->nome }}</h2>
            <p class="text-sm text-slate-500"><x-status-badge :status="$hospedagem->status" /></p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('hospedagens.ficha', $hospedagem) }}" target="_blank" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">🖨 Imprimir ficha</a>
            @if ($hospedagem->estaReservado())
                @can('checkin', $hospedagem)
                    <form method="POST" action="{{ route('hospedagens.checkin', $hospedagem) }}" onsubmit="return confirm('Confirmar check-in agora?')">
                        @csrf
                        <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Fazer check-in</button>
                    </form>
                @endcan
                @can('cancelar', $hospedagem)
                    <form method="POST" action="{{ route('hospedagens.cancelar', $hospedagem) }}" onsubmit="return confirm('Cancelar esta reserva?')">
                        @csrf
                        <button class="rounded-lg border border-rose-300 px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50 dark:border-rose-500/40 dark:hover:bg-rose-500/10">Cancelar reserva</button>
                    </form>
                @endcan
            @elseif ($hospedagem->estaHospedado())
                @can('checkout', $hospedagem)
                    <a href="{{ route('hospedagens.checkout', $hospedagem) }}" class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">Fazer check-out</a>
                @endcan
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
            <h3 class="mb-3 text-sm font-semibold text-slate-700">Dados da estadia</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-400">Hóspedes</dt><dd>{{ $hospedagem->quantidade_hospedes }} ({{ $hospedagem->quantidade_adultos }} adulto(s), {{ $hospedagem->quantidade_criancas }} criança(s), {{ $hospedagem->quantidade_isentos }} isento(s))</dd></div>
                <div class="flex justify-between"><dt class="text-slate-400">Diária</dt><dd>R$ {{ number_format($hospedagem->valor_diaria, 2, ',', '.') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-400">Check-in previsto</dt><dd>{{ $hospedagem->data_checkin_prevista->format('d/m/Y') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-400">Check-out previsto</dt><dd>{{ $hospedagem->data_checkout_prevista->format('d/m/Y') }}</dd></div>
                @if ($hospedagem->data_checkin_real)
                    <div class="flex justify-between"><dt class="text-slate-400">Check-in real</dt><dd>{{ $hospedagem->data_checkin_real->format('d/m/Y H:i') }}</dd></div>
                @endif
                @if ($hospedagem->data_checkout_real)
                    <div class="flex justify-between"><dt class="text-slate-400">Check-out real</dt><dd>{{ $hospedagem->data_checkout_real->format('d/m/Y H:i') }}</dd></div>
                @endif
                @if ($hospedagem->estaFinalizado())
                    <div class="flex justify-between"><dt class="text-slate-400">Total pago</dt><dd class="font-semibold">R$ {{ number_format($hospedagem->valor_total, 2, ',', '.') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">Forma de pagamento</dt><dd>{{ ucfirst(str_replace('_', ' ', $hospedagem->forma_pagamento)) }}</dd></div>
                @endif
                @if ($hospedagem->observacoes)
                    <div class="pt-2"><dt class="text-slate-400">Observações</dt><dd class="mt-1">{{ $hospedagem->observacoes }}</dd></div>
                @endif
            </dl>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-6 lg:col-span-2">
            <h3 class="mb-3 text-sm font-semibold text-slate-700">Consumos</h3>
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase text-slate-400">
                    <tr><th class="py-2">Item</th><th>Qtd</th><th class="text-right">Valor</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse ($hospedagem->consumos as $consumo)
                        <tr>
                            <td class="py-2">{{ $consumo->nomeItem() }}</td>
                            <td class="py-2 text-slate-500">{{ $consumo->quantidade }}</td>
                            <td class="py-2 text-right font-medium">R$ {{ number_format($consumo->subtotal, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-6 text-center text-slate-400">Nenhum consumo lançado ainda.</td></tr>
                    @endforelse
                </tbody>
                @if ($hospedagem->consumos->isNotEmpty())
                    <tfoot>
                        <tr class="border-t border-slate-100 font-semibold">
                            <td class="py-2" colspan="2">Total consumos</td>
                            <td class="py-2 text-right">R$ {{ number_format($hospedagem->consumos->sum('subtotal'), 2, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>

            @if ($hospedagem->estaHospedado())
                @can('consumos', $hospedagem)
                    <div class="mt-5 border-t border-gray-100 pt-5 dark:border-gray-800" x-data="{ tipo: 'produto' }">
                        <p class="mb-3 text-sm font-medium text-slate-700">Lançar consumo</p>
                        <div class="mb-3 flex gap-2 text-xs">
                            <button type="button" @click="tipo = 'produto'" :class="tipo === 'produto' ? 'bg-brand-500 text-white' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'" class="rounded-lg px-3 py-1.5 font-medium">Produto do estoque</button>
                            <button type="button" @click="tipo = 'avulso'" :class="tipo === 'avulso' ? 'bg-brand-500 text-white' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'" class="rounded-lg px-3 py-1.5 font-medium">Item avulso (taxa/serviço)</button>
                        </div>

                        <form method="POST" action="{{ route('hospedagens.consumos', $hospedagem) }}" class="flex flex-wrap items-end gap-3">
                            @csrf
                            <template x-if="tipo === 'produto'">
                                <div class="min-w-48">
                                    <label class="mb-1 block text-xs font-medium text-slate-500">Produto</label>
                                    <select name="produto_id" class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                        <option value="">Selecione...</option>
                                        @foreach ($produtos as $produto)
                                            <option value="{{ $produto->id }}">{{ $produto->nome }} (R$ {{ number_format($produto->preco_venda, 2, ',', '.') }})</option>
                                        @endforeach
                                    </select>
                                </div>
                            </template>
                            <template x-if="tipo === 'avulso'">
                                <div class="flex flex-wrap items-end gap-3">
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-500">Descrição</label>
                                        <input type="text" name="descricao" placeholder="ex: Taxa de late checkout" class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-500">Valor unitário (R$)</label>
                                        <input type="number" step="0.01" min="0" name="valor_unitario" class="w-28 rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                    </div>
                                </div>
                            </template>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-500">Quantidade</label>
                                <input type="number" name="quantidade" min="1" value="1" class="w-20 rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                            </div>
                            <button class="h-[42px] rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white hover:bg-brand-600">Lançar</button>
                        </form>
                    </div>
                @endcan
            @endif
        </div>
    </div>
@endsection
