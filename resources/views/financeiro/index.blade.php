@extends('layouts.app')

@section('titulo', 'Financeiro')

@section('conteudo')
    <div x-data="{ aba: 'pagar', novaPagar: false, novaReceber: false }">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="overflow-hidden rounded-2xl border border-rose-300 bg-rose-100 p-5 shadow-sm dark:border-rose-700 dark:bg-rose-500/[0.15]">
                <div class="-mx-5 -mt-5 mb-4 h-1.5 bg-rose-500"></div>
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Total a pagar (em aberto)</p>
                <p class="mt-2 text-2xl font-bold text-rose-700 dark:text-rose-400">R$ {{ number_format($resumo['a_pagar_total'], 2, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-500">Atrasado: R$ {{ number_format($resumo['a_pagar_atrasado'], 2, ',', '.') }} · Vence hoje: R$ {{ number_format($resumo['a_pagar_vence_hoje'], 2, ',', '.') }}</p>
            </div>
            <div class="overflow-hidden rounded-2xl border border-emerald-300 bg-emerald-100 p-5 shadow-sm dark:border-emerald-700 dark:bg-emerald-500/[0.15]">
                <div class="-mx-5 -mt-5 mb-4 h-1.5 bg-emerald-500"></div>
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Total a receber (em aberto)</p>
                <p class="mt-2 text-2xl font-bold text-emerald-700 dark:text-emerald-400">R$ {{ number_format($resumo['a_receber_total'], 2, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-500">Atrasado: R$ {{ number_format($resumo['a_receber_atrasado'], 2, ',', '.') }} · Vence hoje: R$ {{ number_format($resumo['a_receber_vence_hoje'], 2, ',', '.') }}</p>
            </div>
            <div class="overflow-hidden rounded-2xl border border-sky-300 bg-sky-100 p-5 shadow-sm dark:border-sky-700 dark:bg-sky-500/[0.15]">
                <div class="-mx-5 -mt-5 mb-4 h-1.5 bg-sky-500"></div>
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Saldo projetado</p>
                @php $saldoProjetado = $resumo['a_receber_total'] - $resumo['a_pagar_total']; @endphp
                <p class="mt-2 text-2xl font-bold {{ $saldoProjetado >= 0 ? 'text-sky-700 dark:text-sky-400' : 'text-rose-700 dark:text-rose-400' }}">R$ {{ number_format($saldoProjetado, 2, ',', '.') }}</p>
                <p class="mt-1 text-xs text-slate-500">A receber − a pagar, considerando tudo em aberto</p>
            </div>
        </div>

        <div class="mt-6 flex gap-2 border-b border-gray-200 dark:border-gray-800">
            <button type="button" @click="aba = 'pagar'" :class="aba === 'pagar' ? 'border-rose-500 text-rose-700 dark:text-rose-400' : 'border-transparent text-slate-500 hover:text-slate-700'" class="border-b-2 px-4 py-2.5 text-sm font-medium transition">Contas a pagar</button>
            <button type="button" @click="aba = 'receber'" :class="aba === 'receber' ? 'border-emerald-500 text-emerald-700 dark:text-emerald-400' : 'border-transparent text-slate-500 hover:text-slate-700'" class="border-b-2 px-4 py-2.5 text-sm font-medium transition">Contas a receber</button>
        </div>

        {{-- Contas a pagar --}}
        <div x-show="aba === 'pagar'" x-cloak class="mt-4">
            @can('create', App\Models\ContaPagar::class)
                <div class="mb-4">
                    <button type="button" @click="novaPagar = ! novaPagar" class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">+ Nova conta a pagar</button>

                    <form x-show="novaPagar" x-cloak method="POST" action="{{ route('contas-pagar.store') }}" class="mt-3 grid grid-cols-1 gap-3 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:grid-cols-3">
                        @csrf
                        <input type="text" name="fornecedor" placeholder="Fornecedor" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700">
                        <input type="text" name="descricao" placeholder="Descrição (ex: aluguel de setembro)" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 sm:col-span-2">
                        <select name="categoria" class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700">
                            <option value="">Categoria (opcional)</option>
                            <option value="aluguel">Aluguel</option>
                            <option value="energia">Energia</option>
                            <option value="agua">Água</option>
                            <option value="manutencao">Manutenção</option>
                            <option value="fornecedor">Fornecedor</option>
                            <option value="salario">Salário</option>
                            <option value="imposto">Imposto</option>
                            <option value="outro">Outro</option>
                        </select>
                        <input type="number" step="0.01" name="valor" placeholder="Valor (R$)" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700">
                        <input type="date" name="data_vencimento" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700">
                        <input type="text" name="observacoes" placeholder="Observações (opcional)" class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 sm:col-span-3">
                        <button class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700 sm:col-span-3 sm:w-fit">Cadastrar</button>
                    </form>
                </div>
            @endcan

            <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:bg-white/[0.02] dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3">Fornecedor</th>
                            <th class="px-4 py-3">Descrição</th>
                            <th class="px-4 py-3">Vencimento</th>
                            <th class="px-4 py-3">Valor</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @forelse ($contasPagar as $conta)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-800 dark:text-white/90">{{ $conta->fornecedor }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $conta->descricao }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $conta->data_vencimento->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 font-medium">R$ {{ number_format($conta->valor, 2, ',', '.') }}</td>
                                <td class="px-4 py-3"><x-status-badge :status="$conta->status" /></td>
                                <td class="px-4 py-3">
                                    @if (in_array($conta->status, ['pendente', 'atrasado']))
                                        @can('update', $conta)
                                            <form method="POST" action="{{ route('contas-pagar.pagar', $conta) }}" class="flex items-center gap-2" onsubmit="return confirm('Confirmar pagamento desta conta?')">
                                                @csrf
                                                <select name="forma_pagamento" required class="rounded-lg border border-slate-300 px-2 py-1.5 text-xs dark:border-gray-700">
                                                    <option value="dinheiro">Dinheiro</option>
                                                    <option value="pix">Pix</option>
                                                    <option value="cartao">Cartão</option>
                                                    <option value="transferencia">Transferência</option>
                                                    <option value="boleto">Boleto</option>
                                                </select>
                                                <button class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Dar baixa</button>
                                            </form>
                                        @endcan
                                        @can('delete', $conta)
                                            <form method="POST" action="{{ route('contas-pagar.destroy', $conta) }}" class="mt-1.5 inline" onsubmit="return confirm('Cancelar esta conta?')">
                                                @csrf @method('DELETE')
                                                <button class="text-xs text-slate-400 hover:text-rose-600">Cancelar</button>
                                            </form>
                                        @endcan
                                    @elseif ($conta->status === 'pago')
                                        <span class="text-xs text-slate-400">Pago em {{ $conta->data_pagamento->format('d/m/Y') }}</span>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Nenhuma conta a pagar cadastrada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $contasPagar->links() }}</div>
        </div>

        {{-- Contas a receber --}}
        <div x-show="aba === 'receber'" x-cloak class="mt-4">
            @can('create', App\Models\ContaReceber::class)
                <div class="mb-4">
                    <button type="button" @click="novaReceber = ! novaReceber" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">+ Nova conta a receber</button>

                    <form x-show="novaReceber" x-cloak method="POST" action="{{ route('contas-receber.store') }}" class="mt-3 grid grid-cols-1 gap-3 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:grid-cols-3">
                        @csrf
                        <input type="text" name="pagador" placeholder="Pagador (nome)" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700">
                        <input type="text" name="descricao" placeholder="Descrição (ex: aluguel do espaço de eventos)" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 sm:col-span-2">
                        <select name="categoria" class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700">
                            <option value="">Categoria (opcional)</option>
                            <option value="aluguel_espaco">Aluguel de espaço</option>
                            <option value="patrocinio">Patrocínio</option>
                            <option value="reembolso">Reembolso</option>
                            <option value="outro">Outro</option>
                        </select>
                        <input type="number" step="0.01" name="valor" placeholder="Valor (R$)" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700">
                        <input type="date" name="data_vencimento" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700">
                        <input type="text" name="observacoes" placeholder="Observações (opcional)" class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 sm:col-span-3">
                        <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 sm:col-span-3 sm:w-fit">Cadastrar</button>
                    </form>
                </div>
            @endcan

            <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <table class="min-w-full divide-y divide-slate-100 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:bg-white/[0.02] dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3">Pagador</th>
                            <th class="px-4 py-3">Descrição</th>
                            <th class="px-4 py-3">Vencimento</th>
                            <th class="px-4 py-3">Valor</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @forelse ($contasReceber as $conta)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-800 dark:text-white/90">{{ $conta->nomePagador() }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $conta->descricao }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $conta->data_vencimento->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 font-medium">R$ {{ number_format($conta->valor, 2, ',', '.') }}</td>
                                <td class="px-4 py-3"><x-status-badge :status="$conta->status" /></td>
                                <td class="px-4 py-3">
                                    @if (in_array($conta->status, ['pendente', 'atrasado']))
                                        @can('update', $conta)
                                            <form method="POST" action="{{ route('contas-receber.receber', $conta) }}" class="flex items-center gap-2" onsubmit="return confirm('Confirmar recebimento desta conta?')">
                                                @csrf
                                                <select name="forma_pagamento" required class="rounded-lg border border-slate-300 px-2 py-1.5 text-xs dark:border-gray-700">
                                                    <option value="dinheiro">Dinheiro</option>
                                                    <option value="pix">Pix</option>
                                                    <option value="cartao">Cartão</option>
                                                    <option value="transferencia">Transferência</option>
                                                    <option value="boleto">Boleto</option>
                                                </select>
                                                <button class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Dar baixa</button>
                                            </form>
                                        @endcan
                                        @can('delete', $conta)
                                            <form method="POST" action="{{ route('contas-receber.destroy', $conta) }}" class="mt-1.5 inline" onsubmit="return confirm('Cancelar esta conta?')">
                                                @csrf @method('DELETE')
                                                <button class="text-xs text-slate-400 hover:text-rose-600">Cancelar</button>
                                            </form>
                                        @endcan
                                    @elseif ($conta->status === 'recebido')
                                        <span class="text-xs text-slate-400">Recebido em {{ $conta->data_recebimento->format('d/m/Y') }}</span>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Nenhuma conta a receber cadastrada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $contasReceber->links() }}</div>
        </div>
    </div>
@endsection
