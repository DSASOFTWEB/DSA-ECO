@extends('layouts.pdv')

@section('titulo', 'Lançamento — '.$atendimento->ponto->identificacao)

@php
    $ponto = $atendimento->ponto;
    $rotulo = $ponto->tipo === 'mesa' ? 'MESA' : 'COMANDA';
    $itensAtivos = $atendimento->itens->where('status', 'ativo');
    $itensCancelados = $atendimento->itens->where('status', 'cancelado');
    $emAndamento = $atendimento->estaEmAndamento();
    $produtosJson = $produtos->map(fn ($p) => [
        'id' => $p->id,
        'nome' => $p->nome,
        'sku' => $p->sku,
        'preco' => (float) $p->preco_venda,
        'categoria_id' => $p->categoria_id,
    ])->values();
@endphp

@push('styles')
<style>
:root {
    --food-navy: #0f3d5c;
    --food-teal: #0d9488;
    --food-orange: #ea580c;
    --food-border: #dbe4ee;
    --food-bg: #eef3f7;
    --food-card: #fff;
    --food-muted: #64748b;
}
[x-cloak] { display: none !important; }
.food-lanc { display:flex; flex-direction:column; height:100vh; background:var(--food-bg); color:#0f172a; font-size:13px; }
.food-lanc__header {
    display:flex; align-items:center; justify-content:space-between; gap:12px;
    height:52px; padding:0 14px; background:var(--food-navy); color:#fff; flex-shrink:0;
}
.food-lanc__brand { display:flex; align-items:baseline; gap:10px; min-width:0; }
.food-lanc__brand h1 { font-size:18px; font-weight:800; letter-spacing:.04em; white-space:nowrap; }
.food-lanc__brand span { opacity:.85; font-size:12px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.food-lanc__meta { display:flex; flex-wrap:wrap; gap:8px; justify-content:center; flex:1; }
.food-chip { border-radius:999px; padding:4px 10px; font-size:11px; font-weight:700; background:rgba(255,255,255,.12); }
.food-chip--ok { background:#14532d; color:#bbf7d0; }
.food-chip--warn { background:#92400e; color:#fde68a; }
.food-lanc__acoes-top { display:flex; gap:8px; flex-shrink:0; }
.food-btn-top {
    display:inline-flex; align-items:center; gap:6px; border-radius:8px; border:1px solid rgba(255,255,255,.25);
    background:rgba(255,255,255,.12); color:#fff; padding:7px 12px; font-size:12px; font-weight:700;
}
.food-btn-top:hover { background:rgba(255,255,255,.2); }
.food-btn-top--danger { background:#b91c1c; border-color:#b91c1c; }
.food-lanc__body {
    flex:1; min-height:0; display:grid; gap:10px; padding:10px;
    grid-template-columns: minmax(300px, .95fr) minmax(0, 1.35fr);
}
@media (max-width: 1024px) {
    .food-lanc__body { grid-template-columns: 1fr; grid-template-rows: minmax(280px, 42vh) minmax(0, 1fr); }
}
.food-panel {
    background:var(--food-card); border:1px solid var(--food-border); border-radius:12px;
    display:flex; flex-direction:column; min-height:0; overflow:hidden;
}
.food-panel__head {
    display:flex; align-items:center; justify-content:space-between; gap:8px;
    padding:10px 12px; border-bottom:1px solid var(--food-border); background:#f8fafc; flex-shrink:0;
}
.food-panel__head h2 { font-size:12px; font-weight:800; letter-spacing:.08em; color:var(--food-muted); }
.food-pedido-lista { flex:1; overflow:auto; padding:8px; }
.food-item {
    display:grid; grid-template-columns: 1fr auto auto; gap:8px; align-items:start;
    padding:10px; border-radius:10px; border:1px solid #f1f5f9; margin-bottom:8px; background:#fff;
}
.food-item--cancel { opacity:.45; text-decoration:line-through; }
.food-item__nome { font-weight:700; font-size:14px; }
.food-item__obs { color:var(--food-muted); font-size:11px; margin-top:2px; }
.food-item__qtd { font-weight:800; color:var(--food-navy); white-space:nowrap; }
.food-item__tot { font-weight:800; text-align:right; white-space:nowrap; }
.food-item__acoes { grid-column:1 / -1; display:flex; justify-content:flex-end; }
.food-item__cancel {
    border:0; background:none; color:#dc2626; font-size:11px; font-weight:700; cursor:pointer;
}
.food-totais { border-top:1px solid var(--food-border); padding:12px; background:#f8fafc; flex-shrink:0; }
.food-totais__linha { display:flex; justify-content:space-between; padding:4px 0; color:var(--food-muted); }
.food-totais__geral { display:flex; justify-content:space-between; align-items:end; margin-top:8px; padding-top:10px; border-top:2px solid #cbd5e1; }
.food-totais__geral span { font-size:11px; font-weight:800; letter-spacing:.08em; color:var(--food-muted); }
.food-totais__geral strong { font-size:28px; line-height:1; color:#0f172a; }
.food-acoes-rodape {
    display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; padding:10px 12px;
    border-top:1px solid var(--food-border); flex-shrink:0; background:#fff;
}
.food-acao {
    display:flex; flex-direction:column; align-items:center; justify-content:center; gap:2px;
    min-height:54px; border-radius:10px; border:1px solid var(--food-border); background:#fff;
    font-size:12px; font-weight:800; color:#0f172a; padding:8px;
}
.food-acao small { font-weight:600; color:var(--food-muted); }
.food-acao--teal { background:#ecfdf5; border-color:#99f6e4; color:#0f766e; }
.food-acao--orange { background:#fff7ed; border-color:#fdba74; color:#c2410c; }
.food-acao--navy { background:#eff6ff; border-color:#93c5fd; color:#1d4ed8; }
.food-acao--danger { background:#fef2f2; border-color:#fecaca; color:#b91c1c; }
.food-acao--success { background:#16a34a; border-color:#16a34a; color:#fff; }
.food-acao--success small { color:rgba(255,255,255,.85); }
.food-cats {
    display:flex; gap:8px; overflow-x:auto; padding:10px 12px; border-bottom:1px solid var(--food-border);
    background:#f8fafc; flex-shrink:0;
}
.food-cat {
    flex-shrink:0; border-radius:999px; border:1px solid var(--food-border); background:#fff;
    padding:8px 14px; font-size:12px; font-weight:800; color:#334155; white-space:nowrap;
}
.food-cat--ativa { background:var(--food-navy); border-color:var(--food-navy); color:#fff; }
.food-toolbar {
    display:grid; grid-template-columns: 1fr 100px 1fr auto; gap:8px; padding:10px 12px;
    border-bottom:1px solid var(--food-border); flex-shrink:0;
}
@media (max-width: 640px) {
    .food-toolbar { grid-template-columns: 1fr 1fr; }
}
.food-field { display:flex; flex-direction:column; gap:4px; min-width:0; }
.food-field label { font-size:10px; font-weight:800; letter-spacing:.06em; color:var(--food-muted); }
.food-field input, .food-field select {
    height:42px; border:1px solid var(--food-border); border-radius:8px; padding:0 10px;
    background:#fff; font-size:14px; font-weight:600; width:100%;
}
.food-add-btn {
    height:42px; align-self:end; border:0; border-radius:8px; background:var(--food-teal); color:#fff;
    font-weight:800; padding:0 16px; white-space:nowrap;
}
.food-add-btn:disabled { opacity:.5; }
.food-grade {
    flex:1; overflow:auto; padding:10px; display:grid; gap:10px;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); align-content:start;
}
.food-prod {
    display:flex; flex-direction:column; justify-content:space-between; gap:8px; min-height:110px;
    border:1px solid var(--food-border); border-radius:12px; background:#fff; padding:12px; text-align:left;
    box-shadow:0 1px 2px rgba(15,23,42,.04);
}
.food-prod:hover { border-color:var(--food-teal); box-shadow:0 8px 20px rgba(13,148,136,.12); }
.food-prod:disabled { opacity:.45; cursor:not-allowed; }
.food-prod__nome { font-weight:800; font-size:13px; line-height:1.25; }
.food-prod__preco { font-size:15px; font-weight:800; color:var(--food-navy); }
.food-prod__sku { font-size:10px; color:var(--food-muted); font-weight:600; }
.food-empty { grid-column:1 / -1; text-align:center; color:var(--food-muted); padding:40px 12px; }
.food-modal {
    position:fixed; inset:0; z-index:60; display:flex; align-items:center; justify-content:center;
    background:rgba(15,23,42,.55); padding:16px;
}
.food-modal__card {
    width:min(480px, 100%); background:#fff; border-radius:14px; padding:16px;
    box-shadow:0 20px 50px rgba(15,23,42,.25);
}
.food-modal__card h3 { font-size:16px; font-weight:800; margin-bottom:12px; }
.food-modal__actions { display:flex; gap:8px; justify-content:flex-end; margin-top:14px; }
.food-modal__actions button, .food-modal__actions .food-acao {
    min-height:42px; padding:0 14px; border-radius:8px; font-weight:700;
}
</style>
@endpush

@section('conteudo')
<div
    class="food-lanc"
    x-data="foodLancamento({
        produtos: {{ Js::from($produtosJson) }},
        emAndamento: {{ $emAndamento ? 'true' : 'false' }},
        subtotal: {{ (float) $atendimento->subtotal }},
    })"
    x-cloak
>
    <header class="food-lanc__header">
        <div class="food-lanc__brand">
            <h1>{{ $rotulo }} {{ $ponto->numero }}</h1>
            <span>{{ $atendimento->unidade->nome }} · {{ $atendimento->abertoPor->name }} · {{ $atendimento->aberto_em->format('d/m H:i') }}</span>
        </div>
        <div class="food-lanc__meta">
            <span class="food-chip">{{ $atendimento->quantidade_pessoas }} pessoa(s)</span>
            @if($atendimento->status === 'pre_fechado')
                <span class="food-chip food-chip--warn">Pré-fechada</span>
            @else
                <span class="food-chip food-chip--ok">{{ ucfirst(str_replace('_', ' ', $atendimento->status)) }}</span>
            @endif
        </div>
        <div class="food-lanc__acoes-top">
            <a href="{{ route('food.index', ['unidade_id' => $atendimento->unidade_id, 'tipo' => $ponto->tipo]) }}" class="food-btn-top">Mapa</a>
            <a href="{{ route('food.atendimentos.conta', $atendimento) }}" class="food-btn-top" target="_blank">Conta</a>
        </div>
    </header>

    <div class="food-lanc__body">
        {{-- Painel do pedido (esquerda — referência ZeusFOOD) --}}
        <aside class="food-panel">
            <div class="food-panel__head">
                <h2>PEDIDO</h2>
                <span class="text-xs font-bold text-slate-500">{{ $itensAtivos->count() }} item(ns)</span>
            </div>

            <div class="food-pedido-lista">
                @forelse($atendimento->itens as $item)
                    <article @class(['food-item', 'food-item--cancel' => $item->status === 'cancelado'])>
                        <div>
                            <div class="food-item__nome">{{ $item->produto->nome }}</div>
                            @if($item->observacao)<div class="food-item__obs">{{ $item->observacao }}</div>@endif
                        </div>
                        <div class="food-item__qtd">× {{ rtrim(rtrim(number_format((float)$item->quantidade, 3, ',', '.'), '0'), ',') }}</div>
                        <div class="food-item__tot">R$ {{ number_format((float)$item->subtotal, 2, ',', '.') }}</div>
                        @if($item->status === 'ativo' && $emAndamento)
                            <div class="food-item__acoes">
                                <form method="POST" action="{{ route('food.atendimentos.itens.cancelar', [$atendimento, $item]) }}">
                                    @csrf @method('DELETE')
                                    <input type="hidden" name="motivo" value="Cancelado pelo operador">
                                    <button type="submit" class="food-item__cancel" onclick="return confirm('Cancelar este item?')">Cancelar item</button>
                                </form>
                            </div>
                        @endif
                    </article>
                @empty
                    <p class="food-empty">Nenhum item lançado. Toque em um produto à direita.</p>
                @endforelse

                @if($itensCancelados->isNotEmpty())
                    <p class="mt-2 px-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                        Inclui {{ $itensCancelados->count() }} cancelado(s) no histórico
                    </p>
                @endif
            </div>

            <div class="food-totais">
                <div class="food-totais__linha"><span>Subtotal</span><span>R$ {{ number_format((float)$atendimento->subtotal, 2, ',', '.') }}</span></div>
                <div class="food-totais__geral">
                    <span>TOTAL</span>
                    <strong>R$ {{ number_format((float)$atendimento->valor_total, 2, ',', '.') }}</strong>
                </div>
            </div>

            @if($emAndamento)
            <div class="food-acoes-rodape">
                <form method="POST" action="{{ route('food.atendimentos.pre-fechar', $atendimento) }}">
                    @csrf
                    <button type="submit" class="food-acao food-acao--orange w-full">Pré-fechamento <small>F5</small></button>
                </form>
                <button type="button" class="food-acao food-acao--navy" @click="painel = 'transferir'">Transferir / Juntar <small>F8</small></button>
                <a href="{{ route('food.atendimentos.conta', $atendimento) }}" class="food-acao food-acao--teal" target="_blank">Imprimir conta <small>pré-conta</small></a>
                <button type="button" class="food-acao food-acao--success" @click="painel = 'fechar'">Fechar conta <small>F6</small></button>
            </div>
            @endif
        </aside>

        {{-- Grade categorias / produtos (direita — touch ZeusFOOD) --}}
        <section class="food-panel">
            <div class="food-panel__head">
                <h2>LANÇAMENTO DE PRODUTOS</h2>
                <span class="text-xs font-bold text-slate-500">toque para adicionar</span>
            </div>

            <div class="food-cats">
                <button type="button" class="food-cat" :class="{ 'food-cat--ativa': categoriaId === null }" @click="categoriaId = null">Todos</button>
                @foreach($categorias as $categoria)
                    <button type="button" class="food-cat" :class="{ 'food-cat--ativa': categoriaId === {{ $categoria->id }} }" @click="categoriaId = {{ $categoria->id }}">
                        {{ $categoria->nome }}
                    </button>
                @endforeach
                @if($produtos->contains(fn ($p) => blank($p->categoria_id)))
                    <button type="button" class="food-cat" :class="{ 'food-cat--ativa': categoriaId === 0 }" @click="categoriaId = 0">Sem categoria</button>
                @endif
            </div>

            @if($emAndamento)
            <form method="POST" action="{{ route('food.atendimentos.itens.store', $atendimento) }}" class="food-toolbar" id="form-lancar-item">
                @csrf
                <input type="hidden" name="produto_id" :value="produtoId" required>
                <div class="food-field">
                    <label>Busca / código</label>
                    <input type="search" x-model="busca" placeholder="Nome ou SKU" autocomplete="off" @keydown.enter.prevent="adicionarPrimeiro()">
                </div>
                <div class="food-field">
                    <label>Qtde</label>
                    <input type="number" name="quantidade" x-model.number="quantidade" min="1" step="1" required>
                </div>
                <div class="food-field">
                    <label>Observação</label>
                    <input type="text" name="observacao" x-model="observacao" maxlength="500" placeholder="Ex.: sem cebola">
                </div>
                <button type="submit" class="food-add-btn" :disabled="!produtoId || !emAndamento">Adicionar</button>
                <input type="hidden" name="desconto" value="0">
            </form>
            @endif

            <div class="food-grade">
                <template x-for="produto in produtosFiltrados" :key="produto.id">
                    <button
                        type="button"
                        class="food-prod"
                        :disabled="!emAndamento"
                        @click="selecionarProduto(produto)"
                        @dblclick="lancarRapido(produto)"
                    >
                        <div>
                            <div class="food-prod__nome" x-text="produto.nome"></div>
                            <div class="food-prod__sku" x-show="produto.sku" x-text="produto.sku"></div>
                        </div>
                        <div class="food-prod__preco" x-text="formatarPreco(produto.preco)"></div>
                    </button>
                </template>
                <p class="food-empty" x-show="produtosFiltrados.length === 0">Nenhum produto nesta categoria.</p>
            </div>
        </section>
    </div>

    @if($emAndamento)
    {{-- Modal transferir --}}
    <div class="food-modal" x-show="painel === 'transferir'" x-transition @keydown.escape.window="painel = null">
        <div class="food-modal__card" @click.outside="painel = null">
            <h3>Transferir ou juntar</h3>
            <form method="POST" action="{{ route('food.atendimentos.transferir', $atendimento) }}" class="space-y-3">
                @csrf
                <select name="ponto_destino_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Selecione o destino</option>
                    @foreach($destinos as $destino)
                        <option value="{{ $destino->id }}">{{ $destino->identificacao }} — {{ $destino->status }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-slate-500">Destino livre transfere a conta. Destino ocupado junta os itens.</p>
                <div class="food-modal__actions">
                    <button type="button" class="food-acao" @click="painel = null">Cancelar</button>
                    <button type="submit" class="food-acao food-acao--navy" onclick="return confirm('Confirmar transferência?')">Confirmar</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal fechar --}}
    <div class="food-modal" x-show="painel === 'fechar'" x-transition @keydown.escape.window="painel = null">
        <div class="food-modal__card" @click.outside="painel = null"
             x-data="{ desconto: 0, taxa: 10, subtotal: {{ (float) $atendimento->subtotal }}, get total(){ return Math.max(0, this.subtotal + this.subtotal * this.taxa / 100 - this.desconto).toFixed(2) } }">
            <h3>Fechar {{ strtolower($rotulo) }}</h3>
            <form method="POST" action="{{ route('food.atendimentos.fechar', $atendimento) }}" class="space-y-3">
                @csrf
                <label class="block text-sm font-medium">Caixa
                    <select name="caixa_id" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                        <option value="">Selecione</option>
                        @foreach($caixas as $caixa)
                            <option value="{{ $caixa->id }}">Caixa #{{ $caixa->id }} {{ $caixa->terminal?->nome }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="text-sm font-medium">Desconto
                        <input name="desconto" type="number" min="0" step="0.01" x-model.number="desconto" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                    </label>
                    <label class="text-sm font-medium">Serviço %
                        <input name="percentual_servico" type="number" min="0" max="100" step="0.01" x-model.number="taxa" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                    </label>
                </div>
                <label class="block text-sm font-medium">Forma
                    <select name="pagamentos[0][forma]" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                        <option value="dinheiro">Dinheiro</option>
                        <option value="pix">Pix</option>
                        <option value="credito">Crédito</option>
                        <option value="debito">Débito</option>
                        <option value="fiado">Fiado</option>
                        <option value="outro">Outro</option>
                    </select>
                </label>
                <label class="block text-sm font-medium">Valor
                    <input name="pagamentos[0][valor]" type="number" min="0.01" step="0.01" required :value="total" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                </label>
                <div class="rounded-lg bg-slate-100 p-3 text-right text-lg font-bold">Total: R$ <span x-text="total.replace('.', ',')"></span></div>
                @if($caixas->isEmpty())
                    <p class="text-xs text-rose-600">Abra um caixa nesta unidade antes de fechar.</p>
                @endif
                <div class="food-modal__actions">
                    <button type="button" class="food-acao" @click="painel = null">Voltar</button>
                    <button type="submit" class="food-acao food-acao--success" @disabled($caixas->isEmpty())>Confirmar fechamento</button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
function foodLancamento({ produtos, emAndamento, subtotal }) {
    return {
        produtos,
        emAndamento,
        subtotal,
        categoriaId: null,
        busca: '',
        quantidade: 1,
        observacao: '',
        produtoId: null,
        painel: null,
        get produtosFiltrados() {
            const termo = this.busca.trim().toLowerCase();
            return this.produtos.filter((p) => {
                const catOk = this.categoriaId === null
                    || (this.categoriaId === 0 && !p.categoria_id)
                    || p.categoria_id === this.categoriaId;
                if (!catOk) return false;
                if (!termo) return true;
                return (p.nome || '').toLowerCase().includes(termo)
                    || (p.sku || '').toLowerCase().includes(termo);
            });
        },
        formatarPreco(valor) {
            return 'R$ ' + Number(valor).toFixed(2).replace('.', ',');
        },
        selecionarProduto(produto) {
            if (!this.emAndamento) return;
            this.produtoId = produto.id;
            this.busca = produto.nome;
        },
        adicionarPrimeiro() {
            const primeiro = this.produtosFiltrados[0];
            if (!primeiro) return;
            this.selecionarProduto(primeiro);
            this.$nextTick(() => document.getElementById('form-lancar-item')?.requestSubmit());
        },
        lancarRapido(produto) {
            if (!this.emAndamento) return;
            this.selecionarProduto(produto);
            this.$nextTick(() => document.getElementById('form-lancar-item')?.requestSubmit());
        },
    };
}
</script>
@endpush
