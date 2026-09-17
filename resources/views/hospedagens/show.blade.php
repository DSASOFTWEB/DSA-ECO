@extends('layouts.app')

@section('titulo', 'Hospedagem — Quarto '.$hospedagem->quarto->numero)

@section('conteudo')
@php
    $produtosJson = $produtos->map(fn ($p) => [
        'id' => $p->id,
        'nome' => $p->nome,
        'sku' => $p->sku,
        'preco' => (float) $p->preco_venda,
        'categoria_id' => $p->categoria_id,
        'imagem_url' => $p->imagem_url,
        'estoque' => $p->controla_estoque ? (int) $p->estoque_atual : null,
    ])->values();
@endphp

<style>
    .hp-grade { display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:.75rem; }
    .hp-prod { display:flex; flex-direction:column; justify-content:space-between; gap:.5rem; min-height:110px; border:1px solid #e2e8f0; border-radius:1rem; background:#fff; padding:.85rem; text-align:left; transition:border-color .15s, box-shadow .15s; }
    .hp-prod:hover { border-color:#0ea5e9; box-shadow:0 8px 20px rgba(14,165,233,.12); }
    .hp-prod.is-selected { border-color:#0284c7; background:#f0f9ff; ring:2px; }
    .hp-prod:disabled { opacity:.45; cursor:not-allowed; }
    .dark .hp-prod { background:rgba(255,255,255,.03); border-color:#1f2937; }
    .dark .hp-prod.is-selected { background:rgba(14,165,233,.12); border-color:#38bdf8; }
</style>

    <div class="mb-4">
        <a href="{{ url()->previous(route('hospedagens.index')) }}" class="text-sm text-brand-600 hover:underline">&larr; Voltar</a>
    </div>

    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-2xl font-bold tracking-tight text-gray-800 dark:text-white/90">Quarto {{ $hospedagem->quarto->numero }}</h2>
                <x-status-badge :status="$hospedagem->status" />
                @if ($hospedagem->quarto->precisa_limpeza)
                    <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">Limpeza solicitada</span>
                @endif
                @if ($hospedagem->quarto->nao_perturbe)
                    <span class="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-800 dark:bg-rose-500/15 dark:text-rose-300">Não perturbe</span>
                @endif
            </div>
            <p class="mt-1 text-base font-medium text-slate-700 dark:text-slate-200">
                Hóspede: <span class="font-semibold">{{ $hospedagem->cliente->nome }}</span>
            </p>
            <p class="text-sm text-slate-500">
                {{ $hospedagem->quantidade_hospedes }} pessoa(s)
                · {{ $hospedagem->data_checkin_prevista->format('d/m/Y') }} → {{ $hospedagem->data_checkout_prevista->format('d/m/Y') }}
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            @if ($hospedagem->estaHospedado())
                @can('sinaisQuarto', $hospedagem)
                    <form method="POST" action="{{ route('hospedagens.solicitar-limpeza', $hospedagem) }}">
                        @csrf
                        <button
                            type="submit"
                            @disabled($hospedagem->quarto->precisa_limpeza)
                            class="rounded-lg border border-amber-400 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-amber-500/40 dark:bg-amber-500/10 dark:text-amber-200"
                        >
                            Solicitar limpeza
                        </button>
                    </form>
                    <form method="POST" action="{{ route('hospedagens.nao-perturbe', $hospedagem) }}">
                        @csrf
                        <input type="hidden" name="ativo" value="{{ $hospedagem->quarto->nao_perturbe ? 0 : 1 }}">
                        <button
                            type="submit"
                            class="rounded-lg border px-4 py-2 text-sm font-semibold {{ $hospedagem->quarto->nao_perturbe ? 'border-rose-500 bg-rose-600 text-white hover:bg-rose-700' : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200' }}"
                        >
                            {{ $hospedagem->quarto->nao_perturbe ? 'Remover Não perturbe' : 'Não perturbe' }}
                        </button>
                    </form>
                @endcan
            @endif

            <a href="{{ route('hospedagens.ficha', $hospedagem) }}" target="_blank" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">🖨 Ficha</a>
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
                        <button class="rounded-lg border border-rose-300 px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50">Cancelar</button>
                    </form>
                @endcan
            @elseif ($hospedagem->estaHospedado())
                @can('checkout', $hospedagem)
                    <button type="button" @click="$dispatch('open-modal', 'fechar-conta')" class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700">Fechar conta</button>
                @endcan
            @endif

            @if ($hospedagem->estaHospedado() || $hospedagem->estaFinalizado())
                @can('emitirFiscal', $hospedagem)
                    <form method="POST" action="{{ route('hospedagens.emitir-nfce', $hospedagem) }}" onsubmit="return confirm('Emitir NFC-e dos consumos de produto?')">
                        @csrf
                        <button type="submit" @disabled(count($itensNfce) === 0) class="rounded-lg border border-amber-400 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-50">NFC-e</button>
                    </form>
                    <form method="POST" action="{{ route('hospedagens.emitir-nfse', $hospedagem) }}" onsubmit="return confirm('Emitir NFS-e das diárias e serviços?')">
                        @csrf
                        <button type="submit" @disabled(count($itensNfse) === 0) class="rounded-lg border border-violet-400 bg-violet-50 px-4 py-2 text-sm font-semibold text-violet-800 hover:bg-violet-100 disabled:cursor-not-allowed disabled:opacity-50">NFS-e</button>
                    </form>
                @endcan
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
        {{-- Painel hóspede + consumos --}}
        <div class="space-y-4 xl:col-span-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-200">Hóspede</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-400">Nome</dt><dd class="text-right font-medium">{{ $hospedagem->cliente->nome }}</dd></div>
                    @if ($hospedagem->cliente->cpf)
                        <div class="flex justify-between gap-3"><dt class="text-slate-400">CPF</dt><dd class="text-right">{{ $hospedagem->cliente->cpf }}</dd></div>
                    @endif
                    @if ($hospedagem->cliente->telefone)
                        <div class="flex justify-between gap-3"><dt class="text-slate-400">Telefone</dt><dd class="text-right">{{ $hospedagem->cliente->telefone }}</dd></div>
                    @endif
                    <div class="flex justify-between gap-3"><dt class="text-slate-400">Adultos / crianças</dt><dd class="text-right">{{ $hospedagem->quantidade_adultos }} / {{ $hospedagem->quantidade_criancas }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-400">Diária</dt><dd class="text-right">R$ {{ number_format($hospedagem->valor_diaria, 2, ',', '.') }}</dd></div>
                    @if ($hospedagem->data_checkin_real)
                        <div class="flex justify-between gap-3"><dt class="text-slate-400">Check-in</dt><dd class="text-right">{{ $hospedagem->data_checkin_real->format('d/m/Y H:i') }}</dd></div>
                    @endif
                    @if ($hospedagem->observacoes)
                        <div class="border-t border-slate-100 pt-2 dark:border-gray-800">
                            <dt class="text-slate-400">Observações</dt>
                            <dd class="mt-1 text-slate-700 dark:text-slate-300">{{ $hospedagem->observacoes }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Consumos</h3>
                    <span class="text-xs text-slate-400">{{ $hospedagem->consumos->count() }} item(ns)</span>
                </div>
                <div class="max-h-[28rem] overflow-y-auto">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 bg-white text-left text-xs uppercase text-slate-400 dark:bg-gray-900">
                            <tr><th class="py-2">Item</th><th>Qtd</th><th class="text-right">Valor</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50 dark:divide-gray-800">
                            @forelse ($hospedagem->consumos as $consumo)
                                <tr>
                                    <td class="py-2 pr-2">{{ $consumo->nomeItem() }}</td>
                                    <td class="py-2 text-slate-500">{{ $consumo->quantidade }}</td>
                                    <td class="py-2 text-right font-medium">R$ {{ number_format($consumo->subtotal, 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="py-8 text-center text-slate-400">Nenhum consumo lançado.</td></tr>
                            @endforelse
                        </tbody>
                        @if ($hospedagem->consumos->isNotEmpty())
                            <tfoot>
                                <tr class="border-t border-slate-100 font-semibold dark:border-gray-800">
                                    <td class="py-2" colspan="2">Total</td>
                                    <td class="py-2 text-right">R$ {{ number_format($hospedagem->consumos->sum('subtotal'), 2, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>

        {{-- Portal de lançamento --}}
        <div class="xl:col-span-8">
            @if ($hospedagem->estaHospedado())
                @can('consumos', $hospedagem)
                    <div
                        class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-6"
                        x-data="hospedagemConsumoPortal(@js([
                            'produtos' => $produtosJson,
                            'podeLancar' => true,
                        ]))"
                    >
                        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Portal de consumo</h3>
                                <p class="text-lg font-semibold text-slate-800 dark:text-white">Pesquisar e lançar produtos</p>
                            </div>
                            <div class="flex gap-2 text-xs">
                                <button type="button" @click="modo = 'produto'" :class="modo === 'produto' ? 'bg-brand-500 text-white' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'" class="rounded-lg px-3 py-1.5 font-medium">Estoque</button>
                                <button type="button" @click="modo = 'avulso'" :class="modo === 'avulso' ? 'bg-brand-500 text-white' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'" class="rounded-lg px-3 py-1.5 font-medium">Avulso</button>
                            </div>
                        </div>

                        <div x-show="modo === 'produto'" class="space-y-4">
                            <form method="POST" action="{{ route('hospedagens.consumos', $hospedagem) }}" id="form-consumo-hospedagem" class="grid grid-cols-1 gap-3 sm:grid-cols-12">
                                @csrf
                                <input type="hidden" name="produto_id" :value="produtoId">
                                <div class="sm:col-span-7">
                                    <label class="mb-1 block text-xs font-medium text-slate-500">Buscar produto</label>
                                    <input
                                        type="search"
                                        x-model="busca"
                                        @input="onBusca()"
                                        @keydown.enter.prevent="adicionarPrimeiro()"
                                        placeholder="Digite nome, SKU ou código…"
                                        autocomplete="off"
                                        class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                    >
                                    <p class="mt-1 text-xs text-slate-400">
                                        <span x-text="produtosFiltrados.length"></span> resultado(s)
                                        <template x-if="busca.trim()">
                                            <span> para “<span class="font-medium text-slate-600 dark:text-slate-300" x-text="busca.trim()"></span>”</span>
                                        </template>
                                        · clique no card ou Enter para lançar
                                    </p>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="mb-1 block text-xs font-medium text-slate-500">Qtd</label>
                                    <input type="number" name="quantidade" x-model.number="quantidade" min="1" max="999" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                </div>
                                <div class="sm:col-span-3 flex items-end">
                                    <button type="submit" :disabled="!produtoId" class="h-[42px] w-full rounded-xl bg-brand-500 px-4 text-sm font-semibold text-white hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-50">Lançar</button>
                                </div>
                            </form>

                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="rounded-full px-3 py-1 text-xs font-semibold" :class="categoriaId === null ? 'bg-sky-600 text-white' : 'bg-slate-100 text-slate-600 dark:bg-gray-800 dark:text-gray-300'" @click="categoriaId = null">Todos</button>
                                @foreach ($categorias as $categoria)
                                    <button type="button" class="rounded-full px-3 py-1 text-xs font-semibold" :class="categoriaId === {{ $categoria->id }} ? 'bg-sky-600 text-white' : 'bg-slate-100 text-slate-600 dark:bg-gray-800 dark:text-gray-300'" @click="categoriaId = {{ $categoria->id }}">{{ $categoria->nome }}</button>
                                @endforeach
                            </div>

                            <div class="hp-grade">
                                <template x-for="produto in produtosFiltrados" :key="produto.id">
                                    <button
                                        type="button"
                                        class="hp-prod"
                                        :class="{ 'is-selected': produtoId === produto.id }"
                                        @click="selecionarProduto(produto)"
                                        @dblclick="lancarRapido(produto)"
                                    >
                                        <div>
                                            <div class="text-sm font-bold text-slate-800 dark:text-white" x-text="produto.nome"></div>
                                            <div class="text-[11px] font-medium text-slate-400" x-show="produto.sku" x-text="produto.sku"></div>
                                        </div>
                                        <div class="flex items-end justify-between gap-2">
                                            <span class="text-sm font-extrabold text-sky-700 dark:text-sky-300" x-text="formatarPreco(produto.preco)"></span>
                                            <span class="text-[10px] text-slate-400" x-show="produto.estoque !== null" x-text="'Est. ' + produto.estoque"></span>
                                        </div>
                                    </button>
                                </template>
                            </div>
                            <p class="py-6 text-center text-sm text-slate-400" x-show="produtosFiltrados.length === 0">Nenhum produto encontrado para o termo digitado.</p>
                        </div>

                        <div x-show="modo === 'avulso'" x-cloak>
                            <form method="POST" action="{{ route('hospedagens.consumos', $hospedagem) }}" class="grid grid-cols-1 gap-3 sm:grid-cols-12">
                                @csrf
                                <div class="sm:col-span-6">
                                    <label class="mb-1 block text-xs font-medium text-slate-500">Descrição</label>
                                    <input type="text" name="descricao" required placeholder="ex: Taxa de late checkout" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                </div>
                                <div class="sm:col-span-3">
                                    <label class="mb-1 block text-xs font-medium text-slate-500">Valor unitário (R$)</label>
                                    <input type="number" step="0.01" min="0" name="valor_unitario" required class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                </div>
                                <div class="sm:col-span-1">
                                    <label class="mb-1 block text-xs font-medium text-slate-500">Qtd</label>
                                    <input type="number" name="quantidade" min="1" value="1" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                </div>
                                <div class="sm:col-span-2 flex items-end">
                                    <button class="h-[42px] w-full rounded-xl bg-brand-500 px-4 text-sm font-semibold text-white hover:bg-brand-600">Lançar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endcan
            @else
                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-sm text-slate-500 dark:border-gray-700 dark:bg-white/[0.02]">
                    O portal de consumo fica disponível após o check-in.
                </div>
            @endif

            @if ($hospedagem->documentosFiscais->isNotEmpty())
                <div class="mt-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
                    <h3 class="mb-3 text-sm font-semibold text-slate-700">Documentos fiscais</h3>
                    <ul class="divide-y divide-slate-100 text-sm dark:divide-gray-800">
                        @foreach ($hospedagem->documentosFiscais as $doc)
                            <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                                <span class="font-medium uppercase">{{ $doc->modelo }}</span>
                                <span class="text-slate-500">nº {{ $doc->numero ?? '—' }} · série {{ $doc->serie ?? '—' }}</span>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold uppercase text-slate-600">{{ $doc->status }}</span>
                                <span class="text-slate-700">R$ {{ number_format($doc->valor_total, 2, ',', '.') }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>

    @if ($hospedagem->estaHospedado() && $checkout)
        @can('checkout', $hospedagem)
            <x-modal name="fechar-conta" title="Fechar conta — Quarto {{ $hospedagem->quarto->numero }}" maxWidth="lg">
                @if (! $checkout['caixaAberto'] && $checkout['caixasDisponiveis']->isEmpty())
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                        Nenhum caixa aberto no momento.
                        <a href="{{ route('caixas.index') }}" class="font-medium underline">Abrir caixa</a>
                    </div>
                @else
                    @include('hospedagens._checkout_form', [
                        'hospedagem' => $hospedagem,
                        'caixaAberto' => $checkout['caixaAberto'],
                        'caixasDisponiveis' => $checkout['caixasDisponiveis'],
                        'noites' => $checkout['noites'],
                        'quantidadeItensNfce' => $checkout['quantidadeItensNfce'],
                        'quantidadeItensNfse' => $checkout['quantidadeItensNfse'],
                    ])
                @endif
            </x-modal>
        @endcan
    @endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    @if (request()->boolean('fechar') && !empty($checkout))
        window.dispatchEvent(new CustomEvent('open-modal', { detail: 'fechar-conta' }));
    @endif
});

function hospedagemConsumoPortal(cfg) {
    return {
        produtos: cfg.produtos || [],
        podeLancar: !!cfg.podeLancar,
        modo: 'produto',
        categoriaId: null,
        busca: '',
        quantidade: 1,
        produtoId: null,
        get produtosFiltrados() {
            const termo = this.busca.trim().toLowerCase();
            return this.produtos.filter((p) => {
                const catOk = this.categoriaId === null || p.categoria_id === this.categoriaId;
                if (!catOk) return false;
                if (!termo) return true;
                return (p.nome || '').toLowerCase().includes(termo)
                    || (p.sku || '').toLowerCase().includes(termo)
                    || String(p.id).includes(termo);
            });
        },
        onBusca() {
            if (this.produtoId && this.produtosFiltrados.every((p) => p.id !== this.produtoId)) {
                this.produtoId = null;
            }
        },
        formatarPreco(valor) {
            return 'R$ ' + Number(valor).toFixed(2).replace('.', ',');
        },
        selecionarProduto(produto) {
            this.produtoId = produto.id;
            this.busca = produto.nome;
        },
        adicionarPrimeiro() {
            const primeiro = this.produtosFiltrados[0];
            if (!primeiro) return;
            this.selecionarProduto(primeiro);
            this.$nextTick(() => document.getElementById('form-consumo-hospedagem')?.requestSubmit());
        },
        lancarRapido(produto) {
            this.selecionarProduto(produto);
            this.$nextTick(() => document.getElementById('form-consumo-hospedagem')?.requestSubmit());
        },
    };
}
</script>
@endpush
