@extends('layouts.app')

@section('titulo', 'PDV — Nova venda')

@section('conteudo')
    @if (! $caixaAberto && $caixasDisponiveis->count() > 1)
        {{-- Mais de um caixa aberto disponível pro operador (um por terminal): precisa escolher em qual terminal está vendendo. --}}
        <div class="mx-auto max-w-md rounded-2xl border border-gray-200 bg-white p-6 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-base font-semibold text-slate-700">Você está vendendo em qual terminal?</h2>
            <p class="mt-1 text-sm text-slate-400">Há mais de um caixa aberto agora.</p>
            <form method="GET" action="{{ route('vendas.create') }}" class="mt-4 flex flex-col gap-3">
                <select name="caixa_id" required class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm dark:border-gray-700">
                    <option value="">Selecione o terminal...</option>
                    @foreach ($caixasDisponiveis as $caixa)
                        <option value="{{ $caixa->id }}">{{ $caixa->terminal->nome ?? $caixa->unidade->nome }} ({{ $caixa->unidade->nome }})</option>
                    @endforeach
                </select>
                <button class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-600">Começar a vender</button>
            </form>
        </div>
    @elseif (! $caixaAberto)
        <div class="rounded-2xl border border-warning-200 bg-warning-50 p-5 text-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300">
            @if (request()->user()->unidade)
                Nenhum caixa aberto para a sua unidade. <a href="{{ route('caixas.index') }}" class="font-medium underline">Abra o caixa</a> antes de registrar uma venda.
            @else
                Nenhuma unidade da sua empresa tem caixa aberto no momento. <a href="{{ route('caixas.index') }}" class="font-medium underline">Abra um caixa</a> antes de registrar uma venda.
            @endif
        </div>
    @else
        <div x-data="pdvApp()" @keydown.window="onKeydown($event)" class="space-y-3">

            <div class="flex flex-wrap items-center gap-2 rounded-lg border border-brand-200 bg-brand-50 px-3 py-1.5 text-xs font-medium text-brand-700 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-400">
                <span>Vendendo pelo terminal: <strong>{{ $caixaAberto->terminal->nome ?? $caixaAberto->unidade->nome }}</strong> ({{ $caixaAberto->unidade->nome }})</span>
                @if ($caixasDisponiveis->count() > 1)
                    <a href="{{ route('vendas.create') }}" class="underline">trocar</a>
                @endif
            </div>

            <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                {{-- Carrinho --}}
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900 xl:col-span-2">
                    <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                        <h2 class="text-sm font-semibold text-slate-700">Venda em aberto</h2>
                        <span class="text-xs text-slate-400" x-text="itens.length + ' item(ns)'"></span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/[0.03] dark:text-gray-400">
                                <tr>
                                    <th class="px-3 py-2.5">Código</th>
                                    <th class="px-3 py-2.5">Qtd</th>
                                    <th class="px-3 py-2.5">Nome</th>
                                    <th class="px-3 py-2.5 text-right">Preço</th>
                                    <th class="px-3 py-2.5 text-right">Desconto</th>
                                    <th class="px-3 py-2.5 text-right">Total</th>
                                    <th class="px-3 py-2.5"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <template x-for="(item, indice) in itens" :key="item.chave">
                                    <tr @click="selecionado = indice" class="cursor-pointer transition"
                                        :class="selecionado === indice ? 'bg-brand-50 dark:bg-brand-500/10' : 'hover:bg-gray-50 dark:hover:bg-white/[0.02]'">
                                        <td class="px-3 py-2.5 text-slate-500" x-text="item.codigo"></td>
                                        <td class="px-3 py-2.5 text-slate-500" x-text="item.quantidade"></td>
                                        <td class="px-3 py-2.5 font-medium text-gray-800 dark:text-white/90">
                                            <span x-text="item.nome"></span>
                                            <span x-show="item.tipo === 'entrada'" class="ml-1.5 inline-flex rounded-full bg-cyan-50 px-2 py-0.5 text-[10px] font-semibold text-cyan-700 dark:bg-cyan-500/15 dark:text-cyan-400">entrada</span>
                                        </td>
                                        <td class="px-3 py-2.5 text-right text-slate-500" x-text="formatar(item.preco)"></td>
                                        <td class="px-3 py-2.5 text-right text-amber-600" x-text="item.desconto > 0 ? '-' + formatar(item.desconto) : '—'"></td>
                                        <td class="px-3 py-2.5 text-right font-semibold text-gray-800 dark:text-white/90" x-text="formatar(item.preco * item.quantidade - item.desconto)"></td>
                                        <td class="px-3 py-2.5 text-right">
                                            <button type="button" @click.stop="removerItem(indice)" class="text-slate-300 hover:text-rose-600" title="Remover (Del)">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 6l12 12M18 6 6 18"/></svg>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="itens.length === 0">
                                    <td colspan="7" class="px-4 py-10 text-center text-slate-400">Nenhum item ainda — digite um código ou clique em uma entrada ao lado.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Painel lateral --}}
                <div class="space-y-4">
                    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-400">Código do produto (SKU) ou nome</label>
                        <div class="relative" @click.outside="mostrarSugestoes = false">
                            <input type="text" x-ref="campoCodigo" x-model="codigoDigitado" @input="mostrarSugestoes = true" @focus="mostrarSugestoes = true"
                                   @keydown.enter.prevent="incluirPorCodigo()" @keydown.escape="mostrarSugestoes = false"
                                   placeholder="Digite e pressione Enter..." autofocus autocomplete="off"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm dark:border-gray-700">
                            <ul x-show="mostrarSugestoes && sugestoesItens.length"
                                class="absolute z-10 mt-1 max-h-64 w-full overflow-y-auto divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white shadow-lg dark:divide-gray-800 dark:border-gray-700 dark:bg-gray-800">
                                <template x-for="p in sugestoesItens" :key="p.tipo + '-' + p.id">
                                    <li @click="selecionarItemSugerido(p)" class="flex cursor-pointer items-center justify-between gap-2 px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-white/[0.03]">
                                        <span>
                                            <span x-text="p.nome"></span>
                                            <span x-show="p.tipo === 'entrada'" class="ml-1.5 inline-flex rounded-full bg-cyan-50 px-2 py-0.5 text-[10px] font-semibold text-cyan-700 dark:bg-cyan-500/15 dark:text-cyan-400">entrada</span>
                                            <span class="text-slate-400" x-text="p.sku ? ' · ' + p.sku : ''"></span>
                                        </span>
                                        <span class="shrink-0 font-medium text-slate-500" x-text="formatar(p.preco)"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                        <p x-show="mensagemCodigo" x-text="mensagemCodigo" class="mt-1.5 text-xs text-rose-600"></p>

                        <div class="mt-3 flex items-end gap-2">
                            <div class="flex-1">
                                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-400">Quantidade</label>
                                <input type="number" min="1" x-model.number="quantidadeDigitada" @keydown.enter.prevent="incluirPorCodigo()" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm dark:border-gray-700">
                            </div>
                            <button type="button" @click="incluirPorCodigo()" class="h-[42px] shrink-0 rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white hover:bg-brand-600">+ Incluir</button>
                        </div>
                    </div>

                    @if ($tiposEntrada->isNotEmpty())
                        <div class="rounded-2xl border border-cyan-200 bg-cyan-50/60 p-4 dark:border-cyan-500/30 dark:bg-cyan-500/[0.06]">
                            <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-cyan-700 dark:text-cyan-400">Entradas avulsas</label>
                            <div class="grid grid-cols-2 gap-2">
                                @foreach ($tiposEntrada as $tipo)
                                    <button type="button" @click="incluirEntrada({{ $tipo->id }}, @js($tipo->nome), {{ $tipo->valor }})"
                                            class="rounded-lg border border-cyan-300 bg-white px-3 py-2 text-left text-xs font-medium text-cyan-800 shadow-sm transition hover:bg-cyan-100 dark:border-cyan-500/40 dark:bg-gray-900 dark:text-cyan-300 dark:hover:bg-cyan-500/10">
                                        {{ $tipo->nome }}<br><span class="text-slate-400">R$ {{ number_format($tipo->valor, 2, ',', '.') }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-400">Cliente (opcional)</label>
                        <template x-if="cliente">
                            <div class="flex items-center justify-between rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700">
                                <span x-text="cliente.nome"></span>
                                <button type="button" class="text-xs text-rose-600 hover:underline" @click="cliente = null">trocar</button>
                            </div>
                        </template>
                        <div class="relative" x-show="!cliente">
                            <input type="text" x-model="clienteTermo" @input.debounce.300ms="buscarCliente()" placeholder="Buscar por nome ou CPF..." class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700">
                            <ul x-show="clienteResultados.length" class="absolute z-10 mt-1 w-full divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white shadow-lg dark:divide-gray-800 dark:border-gray-700 dark:bg-gray-800">
                                <template x-for="c in clienteResultados" :key="c.id">
                                    <li @click="cliente = c; clienteResultados = []; clienteTermo = ''" class="cursor-pointer px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-white/[0.03]">
                                        <span x-text="c.nome"></span> <span class="text-slate-400" x-text="c.cpf"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-slate-400">Forma de pagamento</label>
                        <select x-model="formaPagamento" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm dark:border-gray-700">
                            <option value="dinheiro">Dinheiro</option>
                            <option value="cartao_credito">Cartão de crédito</option>
                            <option value="cartao_debito">Cartão de débito</option>
                            <option value="pix">Pix</option>
                        </select>
                        <label class="mb-1.5 mt-3 block text-xs font-semibold uppercase tracking-wide text-slate-400">Observação (opcional)</label>
                        <textarea x-model="observacao" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700"></textarea>
                    </div>
                </div>
            </div>

            {{-- Barra de total / ações — todos os atalhos ficam aqui, junto dos botões --}}
            <div class="flex flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('vendas.index') }}" class="rounded-lg px-4 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-gray-300 dark:hover:bg-white/5">Sair</a>
                        <button type="button" @click="alternarTelaCheia()" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-2.5 text-xs font-medium text-slate-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">
                            <svg x-show="!telaCheia" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 3H5a2 2 0 0 0-2 2v4m18 0V5a2 2 0 0 0-2-2h-4M3 15v4a2 2 0 0 0 2 2h4m10-6v4a2 2 0 0 1-2 2h-4"/></svg>
                            <svg x-cloak x-show="telaCheia" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 3v4a2 2 0 0 1-2 2H3m18 0h-4a2 2 0 0 1-2-2V3M3 15h4a2 2 0 0 1 2 2v4m10-6h-4a2 2 0 0 0-2 2v4"/></svg>
                            <span x-text="telaCheia ? 'Sair da tela cheia' : 'Tela cheia'"></span>
                        </button>
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-sky-50 px-2.5 py-2 text-sm font-semibold text-sky-700 dark:bg-sky-500/15 dark:text-sky-400">
                            <kbd class="pdv-kbd border-sky-400 text-sky-700 dark:border-sky-500 dark:text-sky-400">F2</kbd> Iniciar
                        </span>
                        <button type="button" @click="cancelarVenda()" class="inline-flex items-center gap-1.5 rounded-md bg-rose-50 px-2.5 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-100 dark:bg-rose-500/15 dark:text-rose-400 dark:hover:bg-rose-500/25">
                            <kbd class="pdv-kbd border-rose-400 text-rose-700 dark:border-rose-500 dark:text-rose-400">F3</kbd> Cancelar
                        </button>
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-amber-50 px-2.5 py-2 text-sm font-semibold text-amber-700 dark:bg-amber-500/15 dark:text-amber-400">
                            <kbd class="pdv-kbd border-amber-400 text-amber-700 dark:border-amber-500 dark:text-amber-400">F6</kbd> Desconto
                        </span>
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-violet-50 px-2.5 py-2 text-sm font-semibold text-violet-700 dark:bg-violet-500/15 dark:text-violet-400">
                            <kbd class="pdv-kbd border-violet-400 text-violet-700 dark:border-violet-500 dark:text-violet-400">Del</kbd> Excluir item
                        </span>
                    </div>

                    <div class="text-center sm:text-right">
                        <p class="text-xs uppercase tracking-wide text-slate-400">Valor total a pagar</p>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white" x-text="formatar(total)"></p>
                    </div>
                </div>

                <button type="button" @click="finalizar()" :disabled="itens.length === 0" class="flex w-full items-center justify-center gap-2 rounded-lg bg-emerald-500 px-6 py-3.5 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-emerald-600 disabled:cursor-not-allowed disabled:opacity-50">
                    <kbd class="pdv-kbd border-white/70 text-white">F8</kbd> / <kbd class="pdv-kbd border-white/70 text-white">Enter</kbd> Finalizar venda
                </button>
            </div>

            <form id="form-venda" method="POST" action="{{ route('vendas.store') }}" class="hidden">
                @csrf
                <input type="hidden" name="caixa_id" value="{{ $caixaAberto->id }}">
                <input type="hidden" name="cliente_id" x-bind:value="cliente?.id ?? ''">
                <input type="hidden" name="forma_pagamento" x-bind:value="formaPagamento">
                <input type="hidden" name="observacao" x-bind:value="observacao">
                <template x-for="(item, indice) in itens" :key="'campo-' + item.chave">
                    <span>
                        <input type="hidden" :name="'itens[' + indice + '][' + (item.tipo === 'produto' ? 'produto_id' : 'tipo_entrada_id') + ']'" :value="item.id">
                        <input type="hidden" :name="'itens[' + indice + '][quantidade]'" :value="item.quantidade">
                        <input type="hidden" :name="'itens[' + indice + '][desconto]'" :value="item.desconto">
                    </span>
                </template>
            </form>
        </div>

        <style>.pdv-kbd { display: inline-flex; min-width: 1.75rem; justify-content: center; border-radius: 0.375rem; border: 1.5px solid currentColor; padding: 0.15rem 0.5rem; font-family: ui-monospace, monospace; font-size: 0.875rem; font-weight: 700; background: rgba(255,255,255,.6); }
        html.dark .pdv-kbd { background: rgba(255,255,255,.06); }</style>

        @push('scripts')
            <script>
                document.addEventListener('alpine:init', () => {
                    Alpine.data('pdvApp', () => ({
                        produtos: @json($produtosJson),
                        tiposEntrada: @json($tiposEntradaJson),
                        itens: [],
                        selecionado: null,
                        codigoDigitado: '',
                        quantidadeDigitada: 1,
                        mensagemCodigo: '',
                        mostrarSugestoes: false,
                        cliente: null,
                        clienteTermo: '',
                        clienteResultados: [],
                        formaPagamento: 'dinheiro',
                        observacao: '',
                        proximaChave: 1,
                        telaCheia: false,

                        init() {
                            this.$refs.campoCodigo?.focus();
                            document.addEventListener('fullscreenchange', () => { this.telaCheia = !! document.fullscreenElement; });
                        },

                        alternarTelaCheia() {
                            if (document.fullscreenElement) {
                                document.exitFullscreen();
                            } else {
                                document.documentElement.requestFullscreen().catch(() => {});
                            }
                        },

                        get total() {
                            return this.itens.reduce((soma, item) => soma + (item.preco * item.quantidade - item.desconto), 0);
                        },

                        get sugestoesItens() {
                            const termo = this.codigoDigitado.trim().toLowerCase();
                            if (! termo) return [];

                            const dasEntradas = this.tiposEntrada
                                .filter((t) => t.nome.toLowerCase().includes(termo))
                                .map((t) => ({ tipo: 'entrada', id: t.id, nome: t.nome, sku: null, preco: t.preco }));

                            const doProdutos = this.produtos
                                .filter((p) => (p.sku && p.sku.toLowerCase().includes(termo)) || p.nome.toLowerCase().includes(termo))
                                .map((p) => ({ tipo: 'produto', id: p.id, nome: p.nome, sku: p.sku, preco: p.preco }));

                            return [...dasEntradas, ...doProdutos].slice(0, 8);
                        },

                        selecionarItemSugerido(item) {
                            if (item.tipo === 'entrada') {
                                this.incluirEntrada(item.id, item.nome, item.preco);
                            } else {
                                const qtd = Math.max(1, parseInt(this.quantidadeDigitada) || 1);
                                this.adicionarItem({ tipo: 'produto', id: item.id, codigo: item.sku || ('#' + item.id), nome: item.nome, preco: item.preco, quantidade: qtd, desconto: 0 });
                            }
                            this.codigoDigitado = '';
                            this.quantidadeDigitada = 1;
                            this.mostrarSugestoes = false;
                            this.mensagemCodigo = '';
                            this.$refs.campoCodigo?.focus();
                        },

                        formatar(valor) {
                            return 'R$ ' + Number(valor || 0).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d)\b)/g, '.');
                        },

                        adicionarItem(item) {
                            const existente = this.itens.find((i) => i.tipo === item.tipo && i.id === item.id);
                            if (existente) {
                                existente.quantidade += item.quantidade;
                            } else {
                                item.chave = this.proximaChave++;
                                this.itens.push(item);
                            }
                            this.selecionado = this.itens.length - 1;
                        },

                        incluirPorCodigo() {
                            this.mensagemCodigo = '';
                            const termo = this.codigoDigitado.trim();
                            if (! termo) { this.$refs.campoCodigo?.focus(); return; }

                            const qtd = Math.max(1, parseInt(this.quantidadeDigitada) || 1);
                            const termoLower = termo.toLowerCase();

                            let produto = this.produtos.find((p) => p.sku && p.sku.toLowerCase() === termoLower)
                                || this.produtos.find((p) => String(p.id) === termo);

                            if (! produto) {
                                const porNome = this.produtos.filter((p) => p.nome.toLowerCase().includes(termoLower));
                                if (porNome.length === 1) produto = porNome[0];
                            }

                            if (produto) {
                                this.adicionarItem({ tipo: 'produto', id: produto.id, codigo: produto.sku || ('#' + produto.id), nome: produto.nome, preco: produto.preco, quantidade: qtd, desconto: 0 });
                                this.codigoDigitado = '';
                                this.quantidadeDigitada = 1;
                                this.mostrarSugestoes = false;
                                this.$refs.campoCodigo?.focus();
                                return;
                            }

                            // Não é produto de estoque — tenta como tipo de entrada avulsa (ex: "Entrada Avulsa Adulta").
                            const entradasPorNome = this.tiposEntrada.filter((t) => t.nome.toLowerCase().includes(termoLower));
                            if (entradasPorNome.length === 1) {
                                this.incluirEntrada(entradasPorNome[0].id, entradasPorNome[0].nome, entradasPorNome[0].preco);
                                this.codigoDigitado = '';
                                this.mostrarSugestoes = false;
                                this.$refs.campoCodigo?.focus();
                                return;
                            }

                            this.mensagemCodigo = 'Nada encontrado para "' + termo + '".';
                        },

                        incluirEntrada(id, nome, valor) {
                            const qtd = Math.max(1, parseInt(this.quantidadeDigitada) || 1);
                            this.adicionarItem({ tipo: 'entrada', id, codigo: 'ENT-' + id, nome, preco: valor, quantidade: qtd, desconto: 0 });
                            this.quantidadeDigitada = 1;
                        },

                        removerItem(indice) {
                            this.itens.splice(indice, 1);
                            this.selecionado = null;
                        },

                        aplicarDescontoNoSelecionado() {
                            if (this.selecionado === null || ! this.itens[this.selecionado]) {
                                alert('Selecione um item na lista antes de aplicar desconto (clique na linha).');
                                return;
                            }
                            const item = this.itens[this.selecionado];
                            const bruto = item.preco * item.quantidade;
                            const valor = prompt('Desconto (em R$) para "' + item.nome + '" — máximo ' + this.formatar(bruto) + ':', item.desconto || '0');
                            if (valor === null) return;
                            const numero = parseFloat(valor.replace(',', '.'));
                            if (isNaN(numero) || numero < 0) return;
                            item.desconto = Math.min(numero, bruto);
                        },

                        buscarCliente() {
                            if (this.clienteTermo.trim().length < 2) { this.clienteResultados = []; return; }
                            fetch('{{ route('clientes.buscar') }}?termo=' + encodeURIComponent(this.clienteTermo))
                                .then((r) => r.json())
                                .then((dados) => { this.clienteResultados = dados; });
                        },

                        iniciarVenda() {
                            this.$refs.campoCodigo?.focus();
                            this.$refs.campoCodigo?.select();
                        },

                        cancelarVenda() {
                            if (this.itens.length > 0 && ! confirm('Cancelar esta venda e limpar o carrinho?')) return;
                            this.itens = [];
                            this.selecionado = null;
                            this.cliente = null;
                            this.observacao = '';
                            this.codigoDigitado = '';
                            this.quantidadeDigitada = 1;
                            this.mensagemCodigo = '';
                            this.iniciarVenda();
                        },

                        finalizar() {
                            if (this.itens.length === 0) return;
                            this.$nextTick(() => document.getElementById('form-venda').submit());
                        },

                        onKeydown(evento) {
                            const alvo = evento.target;
                            const emCampoTexto = ['INPUT', 'TEXTAREA', 'SELECT'].includes(alvo.tagName);

                            if (evento.key === 'F2') { evento.preventDefault(); this.iniciarVenda(); return; }
                            if (evento.key === 'F3') { evento.preventDefault(); this.cancelarVenda(); return; }
                            if (evento.key === 'F6') { evento.preventDefault(); this.aplicarDescontoNoSelecionado(); return; }
                            if (evento.key === 'F8') { evento.preventDefault(); this.finalizar(); return; }
                            if (evento.key === 'Delete' && ! emCampoTexto && this.selecionado !== null) {
                                evento.preventDefault();
                                this.removerItem(this.selecionado);
                                return;
                            }
                            if (evento.key === 'Enter' && alvo !== this.$refs.campoCodigo && ! emCampoTexto) {
                                evento.preventDefault();
                                this.finalizar();
                            }
                        },
                    }));
                });
            </script>
        @endpush
    @endif
@endsection
