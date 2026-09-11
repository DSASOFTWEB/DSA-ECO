@extends('layouts.pdv')

@section('titulo', 'PDV — Frente de Caixa')

@push('styles')
<style>
:root {
    --pdv-blue: #1d4ed8;
    --pdv-navy: #1e3a8a;
    --pdv-green: #16a34a;
    --pdv-green-dark: #166534;
    --pdv-red: #dc2626;
    --pdv-border: #e2e8f0;
    --pdv-bg: #f8fafc;
    --pdv-card: #fff;
    --pdv-text: #1e293b;
    --pdv-muted: #64748b;
}
[x-cloak] { display: none !important; }
.pdv-frente { display:flex; flex-direction:column; height:100vh; background:var(--pdv-bg); color:var(--pdv-text); font-size:13px; }
.pdv-frente--bloqueado { pointer-events:none; opacity:.72; }
.pdv-overlay { position:fixed; inset:0; z-index:80; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:12px; background:rgba(15,23,42,.55); color:#fff; font-weight:600; }
.pdv-header { display:flex; align-items:center; justify-content:space-between; gap:12px; height:44px; padding:0 14px; background:var(--pdv-navy); color:#fff; flex-shrink:0; }
.pdv-header__brand { display:flex; align-items:center; gap:8px; font-weight:700; letter-spacing:.02em; }
.pdv-header__title { font-size:15px; }
.pdv-header__divider { opacity:.45; }
.pdv-header__sub { font-weight:500; opacity:.9; font-size:12px; }
.pdv-header__meta { display:flex; align-items:center; gap:10px; font-size:12px; flex:1; justify-content:center; flex-wrap:wrap; }
.pdv-header__meta-sep { opacity:.35; }
.pdv-header__acoes { display:flex; gap:8px; }
.pdv-header__btn { display:inline-flex; align-items:center; gap:6px; border-radius:6px; border:1px solid rgba(255,255,255,.25); background:rgba(255,255,255,.12); color:#fff; padding:5px 10px; font-size:12px; font-weight:600; }
.pdv-header__btn:hover { background:rgba(255,255,255,.2); }
.pdv-header__btn--close { background:#b91c1c; border-color:#b91c1c; }
.pdv-status-badge { display:inline-flex; align-items:center; gap:6px; border-radius:999px; padding:3px 10px; font-size:11px; font-weight:700; letter-spacing:.04em; }
.pdv-status-badge--online { background:#14532d; color:#bbf7d0; }
.pdv-status-badge--offline { background:#7f1d1d; color:#fecaca; }
.pdv-dot { width:7px; height:7px; border-radius:50%; background:currentColor; }
.pdv-body { flex:1; display:grid; grid-template-columns:minmax(0,1.35fr) minmax(320px,.9fr); min-height:0; gap:10px; padding:10px; }
.pdv-esq, .pdv-dir { display:flex; flex-direction:column; gap:10px; min-height:0; }
.pdv-dir { background:#f1f5f9; border:1px solid var(--pdv-border); border-radius:8px; padding:10px; }
.pdv-dir__conteudo { flex:1; display:flex; flex-direction:column; gap:10px; min-height:0; overflow:auto; }
.pdv-card { background:var(--pdv-card); border:1px solid var(--pdv-border); border-radius:8px; padding:10px; }
.pdv-card--flex { flex:1; display:flex; flex-direction:column; min-height:0; }
.pdv-card__label { font-size:11px; font-weight:700; letter-spacing:.08em; color:var(--pdv-muted); margin-bottom:8px; }
.pdv-scan { display:flex; align-items:stretch; border:2px solid var(--pdv-blue); border-radius:8px; overflow:hidden; background:#fff; }
.pdv-scan__barcode { display:grid; place-items:center; width:42px; background:#eff6ff; color:var(--pdv-blue); flex-shrink:0; }
.pdv-scan__input { flex:1; border:0; outline:0; padding:12px 12px; font-size:15px; min-width:0; }
.pdv-scan__btn { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:2px; background:var(--pdv-blue); color:#fff; padding:0 14px; font-size:11px; font-weight:700; border:0; }
.pdv-scan__btn small { opacity:.85; font-weight:600; }
.pdv-sugestoes { list-style:none; margin:6px 0 0; padding:0; max-height:220px; overflow:auto; border:1px solid var(--pdv-border); border-radius:8px; background:#fff; box-shadow:0 8px 24px rgba(15,23,42,.08); }
.pdv-sugestao { display:flex; justify-content:space-between; gap:10px; padding:9px 12px; cursor:pointer; border-bottom:1px solid #f1f5f9; }
.pdv-sugestao:hover, .pdv-sugestao--ativa { background:#eff6ff; }
.pdv-sugestao__nome { font-weight:600; }
.pdv-sugestao__meta { color:var(--pdv-muted); white-space:nowrap; }
.pdv-info-row { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.pdv-info-box { background:#fff; border:1px solid var(--pdv-border); border-radius:8px; padding:10px; }
.pdv-info-box__content { display:flex; align-items:center; gap:10px; }
.pdv-info-ico { width:34px; height:34px; border-radius:8px; display:grid; place-items:center; flex-shrink:0; font-size:16px; }
.pdv-info-ico--ok { background:#dcfce7; color:#166534; }
.pdv-info-ico--warn { background:#fee2e2; color:#991b1b; }
.pdv-info-ico--user { background:#e0e7ff; color:#3730a3; }
.pdv-info-box__texto { flex:1; min-width:0; }
.pdv-info-box__texto strong { display:block; }
.pdv-info-box__texto span { color:var(--pdv-muted); font-size:12px; }
.pdv-info-cli-btn { border:1px solid var(--pdv-border); background:#fff; border-radius:6px; width:32px; height:32px; display:grid; place-items:center; }
.pdv-table-wrap { flex:1; overflow:auto; border:1px solid var(--pdv-border); border-radius:6px; }
.pdv-table { width:100%; border-collapse:collapse; }
.pdv-table th { position:sticky; top:0; background:#f8fafc; text-align:left; font-size:10px; letter-spacing:.06em; color:var(--pdv-muted); padding:8px; border-bottom:1px solid var(--pdv-border); }
.pdv-table td { padding:8px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
.pdv-row--sel { background:#eff6ff !important; }
.pdv-table__empty { text-align:center; color:var(--pdv-muted); padding:28px 8px !important; }
.col-item { width:44px; } .col-cod { width:90px; } .col-qtd { width:110px; } .col-unit, .col-total { width:90px; text-align:right; } .col-del { width:36px; }
.pdv-qtd-ctrl { display:inline-flex; align-items:center; gap:4px; }
.pdv-qtd-btn { width:26px; height:26px; border-radius:6px; border:1px solid var(--pdv-border); background:#fff; font-weight:700; }
.pdv-qtd-val { min-width:28px; text-align:center; font-weight:700; }
.pdv-del { color:#94a3b8; background:none; border:0; }
.pdv-del:hover { color:var(--pdv-red); }
.pdv-acoes { display:flex; flex-wrap:wrap; gap:8px; }
.pdv-acao { display:inline-flex; flex-direction:column; align-items:center; gap:2px; border:1px solid var(--pdv-border); background:#fff; border-radius:8px; padding:8px 10px; min-width:110px; font-size:11px; font-weight:700; color:var(--pdv-text); }
.pdv-acao small { color:var(--pdv-muted); font-weight:600; }
.pdv-acao--primary { border-color:#bfdbfe; background:#eff6ff; color:#1e40af; }
.pdv-acao--danger { border-color:#fecaca; background:#fef2f2; color:#991b1b; }
.pdv-entradas { display:flex; flex-wrap:wrap; gap:6px; }
.pdv-entrada { border:1px solid #a5f3fc; background:#ecfeff; color:#155e75; border-radius:8px; padding:8px 10px; font-size:11px; font-weight:700; text-align:left; }
.pdv-entrada span { display:block; color:#0891b2; font-weight:600; }
.pdv-rapidas { display:grid; grid-template-columns:repeat(3,1fr); gap:8px; }
.pdv-rapida { display:flex; flex-direction:column; align-items:center; gap:2px; border:1px solid var(--pdv-border); background:#fff; border-radius:8px; padding:10px 6px; font-size:11px; font-weight:700; }
.pdv-rapida small { color:var(--pdv-muted); }
.pdv-resumo__linha { display:flex; align-items:center; justify-content:space-between; gap:8px; padding:6px 0; border-bottom:1px dashed #e2e8f0; }
.pdv-resumo__total { display:flex; justify-content:space-between; align-items:end; margin-top:10px; padding-top:10px; border-top:2px solid #cbd5e1; }
.pdv-resumo__total span { font-size:12px; font-weight:700; color:var(--pdv-muted); letter-spacing:.06em; }
.pdv-resumo__total strong { font-size:28px; line-height:1; color:#0f172a; }
.pdv-pgto__especies { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:8px; }
.pdv-pgto-esp { display:flex; flex-direction:column; align-items:flex-start; gap:2px; border:2px solid transparent; border-radius:8px; padding:10px; font-weight:700; color:#fff; text-align:left; }
.pdv-pgto-esp small { opacity:.85; font-weight:600; }
.pdv-pgto-esp--ativa { outline:2px solid #0f172a; outline-offset:1px; }
.pgto-din { background:#15803d; } .pgto-pix { background:#0f766e; } .pgto-cred { background:#1d4ed8; } .pgto-deb { background:#7c3aed; }
.pdv-pgto__totais { display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:10px; }
.pdv-pgto__cel { background:#fff; border:1px solid var(--pdv-border); border-radius:8px; padding:8px; }
.pdv-pgto__cel-lbl { display:block; font-size:10px; letter-spacing:.06em; color:var(--pdv-muted); font-weight:700; margin-bottom:4px; }
.pdv-pgto__cel--alerta strong { color:#b45309; }
.pdv-pgto__cel--troco strong { color:var(--pdv-green); }
.pdv-pgto__input { width:100%; border:1px solid var(--pdv-border); border-radius:6px; padding:7px 8px; }
.pdv-finalizar { margin-top:auto; display:flex; align-items:center; justify-content:center; gap:12px; width:100%; border:0; border-radius:10px; background:var(--pdv-green); color:#fff; padding:16px; font-weight:800; letter-spacing:.04em; }
.pdv-finalizar:hover:not(:disabled) { background:var(--pdv-green-dark); }
.pdv-finalizar:disabled { opacity:.45; cursor:not-allowed; }
.pdv-finalizar__txt { display:flex; flex-direction:column; align-items:flex-start; line-height:1.15; }
.pdv-finalizar small { font-weight:600; opacity:.9; }
.pdv-footer { height:32px; flex-shrink:0; display:flex; align-items:center; gap:12px; padding:0 12px; background:#0f172a; color:#cbd5e1; font-size:11px; }
.pdv-footer__sep { width:1px; height:14px; background:#334155; }
.pdv-footer__shortcuts { margin-left:auto; display:flex; gap:10px; flex-wrap:wrap; }
.pdv-footer kbd, .pdv-kbd { display:inline-block; border:1px solid #64748b; border-radius:4px; padding:0 5px; font-family:ui-monospace,monospace; font-size:10px; }
.pdv-modal { position:fixed; inset:0; z-index:90; display:flex; align-items:center; justify-content:center; background:rgba(15,23,42,.45); padding:16px; }
.pdv-modal__card { width:100%; max-width:420px; background:#fff; border-radius:12px; padding:18px; box-shadow:0 20px 50px rgba(15,23,42,.25); }
.pdv-modal__card h3 { margin:0 0 8px; font-size:16px; }
.pdv-modal__actions { display:flex; justify-content:flex-end; gap:8px; margin-top:14px; }
.pdv-btn { border-radius:8px; border:1px solid var(--pdv-border); background:#fff; padding:8px 12px; font-weight:600; }
.pdv-btn--primary { background:var(--pdv-blue); border-color:var(--pdv-blue); color:#fff; }
.pdv-gate { max-width:480px; margin:10vh auto; background:#fff; border:1px solid var(--pdv-border); border-radius:12px; padding:24px; text-align:center; }
@media (max-width:1100px) {
    .pdv-body { grid-template-columns:1fr; overflow:auto; }
    .pdv-info-row { grid-template-columns:1fr; }
}
</style>
@endpush

@section('conteudo')
    @if (! $caixaAberto && $caixasDisponiveis->count() > 1)
        <div class="pdv-frente">
            <header class="pdv-header">
                <div class="pdv-header__brand"><span class="pdv-header__title">PDV</span><span class="pdv-header__divider">|</span><span class="pdv-header__sub">Escolher terminal</span></div>
                <a href="{{ route('vendas.index') }}" class="pdv-header__btn pdv-header__btn--close">Sair</a>
            </header>
            <div class="pdv-gate">
                <h2 class="text-base font-semibold text-slate-800">Você está vendendo em qual terminal?</h2>
                <p class="mt-1 text-sm text-slate-500">Há mais de um caixa aberto agora.</p>
                <form method="GET" action="{{ route('vendas.create') }}" class="mt-4 flex flex-col gap-3 text-left">
                    <select name="caixa_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                        <option value="">Selecione o terminal...</option>
                        @foreach ($caixasDisponiveis as $caixa)
                            <option value="{{ $caixa->id }}">{{ $caixa->terminal->nome ?? $caixa->unidade->nome }} ({{ $caixa->unidade->nome }})</option>
                        @endforeach
                    </select>
                    <button class="rounded-lg bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800">Começar a vender</button>
                </form>
            </div>
        </div>
    @elseif (! $caixaAberto)
        <div class="pdv-frente">
            <header class="pdv-header">
                <div class="pdv-header__brand"><span class="pdv-header__title">PDV</span><span class="pdv-header__divider">|</span><span class="pdv-header__sub">Caixa fechado</span></div>
                <a href="{{ route('vendas.index') }}" class="pdv-header__btn pdv-header__btn--close">Sair</a>
            </header>
            <div class="pdv-gate">
                <span class="pdv-status-badge pdv-status-badge--offline" style="margin:0 auto 12px;"><span class="pdv-dot"></span>PDV OFFLINE</span>
                <h2 class="text-base font-semibold text-slate-800">Nenhum caixa aberto</h2>
                <p class="mt-2 text-sm text-slate-500">Abra o caixa do terminal antes de registrar vendas.</p>
                <a href="{{ route('caixas.index') }}" class="mt-4 inline-flex rounded-lg bg-sky-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-800">Ir para caixas</a>
            </div>
        </div>
    @else
        <div class="pdv-frente" :class="{ 'pdv-frente--bloqueado': salvando }" x-data="pdvApp()" @keydown.window="onKeydown($event)">
            <div x-show="salvando" class="pdv-overlay" x-cloak>
                <svg class="h-10 w-10 animate-spin text-white" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                <span>Finalizando venda...</span>
            </div>

            <header class="pdv-header">
                <div class="pdv-header__brand">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l3-8H6.4M7 13l-1.5 7h13M10 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"/></svg>
                    <span class="pdv-header__title">PDV</span>
                    <span class="pdv-header__divider">|</span>
                    <span class="pdv-header__sub">Frente de Caixa</span>
                </div>
                <div class="pdv-header__meta">
                    <span>Caixa: <strong>{{ $caixaAberto->terminal->nome ?? $caixaAberto->unidade->nome }}</strong></span>
                    <span class="pdv-header__meta-sep">|</span>
                    <span>Operador: <strong>{{ auth()->user()->name }}</strong></span>
                    <span class="pdv-header__meta-sep">|</span>
                    <span class="pdv-status-badge pdv-status-badge--online"><span class="pdv-dot"></span>PDV ONLINE</span>
                </div>
                <div class="pdv-header__acoes">
                    @if ($caixasDisponiveis->count() > 1)
                        <a href="{{ route('vendas.create') }}" class="pdv-header__btn">Trocar terminal</a>
                    @endif
                    <a href="{{ route('caixas.show', $caixaAberto) }}" class="pdv-header__btn">Caixa</a>
                    <a href="{{ route('vendas.index') }}" class="pdv-header__btn pdv-header__btn--close" title="Sair do PDV">✕</a>
                </div>
            </header>

            <div class="pdv-body">
                <section class="pdv-esq">
                    <div class="pdv-card">
                        <div class="pdv-card__label">LEITURA DE PRODUTO</div>
                        <div class="relative">
                            <div class="pdv-scan">
                                <span class="pdv-scan__barcode">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="1.8" d="M3 5v14M7 5v14M10 5v14M14 5v14M17 5v14M21 5v14"/></svg>
                                </span>
                                <input type="text" x-ref="campoCodigo" x-model="codigoDigitado"
                                       @input="mostrarSugestoes = true" @focus="mostrarSugestoes = true"
                                       @keydown.enter.prevent="incluirPorCodigo()" @keydown.escape="mostrarSugestoes = false"
                                       @keydown.down.prevent="moverSugestao(1)" @keydown.up.prevent="moverSugestao(-1)"
                                       class="pdv-scan__input" placeholder="Bipe o código ou digite nome / SKU" autocomplete="off" autofocus>
                                <button type="button" class="pdv-scan__btn" @click="incluirPorCodigo()">
                                    <span>PESQUISAR</span>
                                    <small>F2</small>
                                </button>
                            </div>
                            <ul x-show="mostrarSugestoes && sugestoesItens.length" class="pdv-sugestoes" @mousedown.prevent x-cloak>
                                <template x-for="(p, si) in sugestoesItens" :key="p.tipo + '-' + p.id">
                                    <li class="pdv-sugestao" :class="{ 'pdv-sugestao--ativa': si === sugestaoIdx }" @click="selecionarItemSugerido(p)">
                                        <span class="pdv-sugestao__nome">
                                            <span x-text="p.nome"></span>
                                            <span x-show="p.tipo === 'entrada'" class="ml-1 rounded bg-cyan-100 px-1.5 py-0.5 text-[10px] font-semibold text-cyan-700">entrada</span>
                                        </span>
                                        <span class="pdv-sugestao__meta">
                                            <span x-text="(p.sku ? p.sku + ' · ' : '') + formatar(p.preco)"></span>
                                            <span x-show="p.tipo === 'produto' && p.controla_estoque" class="ml-2" x-text="'Est: ' + p.estoque"></span>
                                        </span>
                                    </li>
                                </template>
                            </ul>
                            <p x-show="mensagemCodigo" class="mt-2 text-xs font-semibold text-rose-600" x-text="mensagemCodigo" x-cloak></p>
                        </div>
                    </div>

                    <div class="pdv-info-row">
                        <div class="pdv-info-box">
                            <div class="pdv-card__label">STATUS DA LEITURA</div>
                            <div class="pdv-info-box__content">
                                <span class="pdv-info-ico" :class="mensagemCodigo ? 'pdv-info-ico--warn' : 'pdv-info-ico--ok'">✓</span>
                                <div class="pdv-info-box__texto">
                                    <strong x-text="mensagemCodigo ? 'Atenção' : (ultimoItem ? 'Item adicionado' : 'Pronto para leitura')"></strong>
                                    <span x-text="mensagemCodigo || (ultimoItem ? ultimoItem : 'Aguardando próximo produto...')"></span>
                                </div>
                            </div>
                        </div>
                        <div class="pdv-info-box">
                            <div class="pdv-card__label">INFORMAÇÕES DO CLIENTE</div>
                            <div class="pdv-info-box__content">
                                <span class="pdv-info-ico pdv-info-ico--user">👤</span>
                                <div class="pdv-info-box__texto">
                                    <strong x-text="cliente ? cliente.nome : 'Consumidor final'"></strong>
                                    <span x-text="cliente ? (cliente.cpf || 'Sem CPF') : 'Venda sem cliente identificado'"></span>
                                </div>
                                <button type="button" class="pdv-info-cli-btn" title="Buscar cliente (F7)" @click="abrirCliente()">🔍</button>
                                <button type="button" class="pdv-info-cli-btn" x-show="cliente" @click="cliente = null" title="Consumidor final" x-cloak>✕</button>
                            </div>
                        </div>
                    </div>

                    @if ($tiposEntrada->isNotEmpty())
                        <div class="pdv-card">
                            <div class="pdv-card__label">ENTRADAS AVULSAS</div>
                            <div class="pdv-entradas">
                                @foreach ($tiposEntrada as $tipo)
                                    <button type="button" class="pdv-entrada" @click="incluirEntrada({{ $tipo->id }}, @js($tipo->nome), {{ $tipo->valor }})">
                                        {{ $tipo->nome }}
                                        <span>R$ {{ number_format($tipo->valor, 2, ',', '.') }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="pdv-card pdv-card--flex">
                        <div class="pdv-card__label">ITENS DA VENDA</div>
                        <div class="pdv-table-wrap">
                            <table class="pdv-table">
                                <thead>
                                    <tr>
                                        <th class="col-item">ITEM</th>
                                        <th class="col-cod">CÓDIGO</th>
                                        <th>DESCRIÇÃO</th>
                                        <th class="col-qtd">QTD</th>
                                        <th class="col-unit">UNITÁRIO</th>
                                        <th class="col-total">TOTAL</th>
                                        <th class="col-del"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="(item, indice) in itens" :key="item.chave">
                                        <tr :class="{ 'pdv-row--sel': selecionado === indice }" @click="selecionado = indice">
                                            <td class="col-item" x-text="indice + 1"></td>
                                            <td class="col-cod" x-text="item.codigo"></td>
                                            <td>
                                                <span x-text="item.nome"></span>
                                                <span x-show="item.tipo === 'entrada'" class="ml-1 rounded bg-cyan-100 px-1.5 py-0.5 text-[10px] font-semibold text-cyan-700">entrada</span>
                                                <span x-show="item.desconto > 0" class="ml-1 text-[11px] text-amber-600" x-text="'-' + formatar(item.desconto)"></span>
                                            </td>
                                            <td class="col-qtd" @click.stop>
                                                <div class="pdv-qtd-ctrl">
                                                    <button type="button" class="pdv-qtd-btn" @click="alterarQtd(indice, -1)">−</button>
                                                    <span class="pdv-qtd-val" x-text="item.quantidade"></span>
                                                    <button type="button" class="pdv-qtd-btn" @click="alterarQtd(indice, 1)">+</button>
                                                </div>
                                            </td>
                                            <td class="col-unit" x-text="formatar(item.preco)"></td>
                                            <td class="col-total font-semibold" x-text="formatar(item.preco * item.quantidade - item.desconto)"></td>
                                            <td class="col-del">
                                                <button type="button" class="pdv-del" @click.stop="removerItem(indice)" title="Remover">🗑</button>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr x-show="itens.length === 0">
                                        <td colspan="7" class="pdv-table__empty">Nenhum item adicionado — bipe ou pesquise um produto</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="pdv-acoes">
                        <button type="button" class="pdv-acao" :disabled="selecionado === null" @click="selecionado !== null && removerItem(selecionado)">
                            <span>REMOVER ITEM</span><small>DEL</small>
                        </button>
                        <button type="button" class="pdv-acao" :disabled="selecionado === null" @click="aplicarDescontoNoSelecionado()">
                            <span>DESCONTO ITEM</span><small>F6</small>
                        </button>
                        <button type="button" class="pdv-acao pdv-acao--primary" @click="iniciarVenda()">
                            <span>PESQUISAR</span><small>F2</small>
                        </button>
                        <button type="button" class="pdv-acao pdv-acao--danger" @click="cancelarVenda()">
                            <span>CANCELAR VENDA</span><small>ESC</small>
                        </button>
                    </div>
                </section>

                <aside class="pdv-dir">
                    <div class="pdv-dir__conteudo">
                        <div class="pdv-rapidas">
                            <button type="button" class="pdv-rapida" @click="abrirCliente()"><span>CLIENTE</span><small>F7</small></button>
                            <button type="button" class="pdv-rapida" @click="dlgObs = true"><span>OBSERVAÇÕES</span><small>—</small></button>
                            <button type="button" class="pdv-rapida" @click="dlgDesconto = true"><span>DESCONTO</span><small>—</small></button>
                        </div>

                        <div class="pdv-card">
                            <div class="pdv-card__label">RESUMO DA VENDA</div>
                            <div class="pdv-resumo__linha"><span>Quantidade de itens</span><strong x-text="itens.length"></strong></div>
                            <div class="pdv-resumo__linha"><span>Subtotal</span><strong x-text="formatar(subtotal)"></strong></div>
                            <div class="pdv-resumo__linha"><span>Desconto</span><strong class="text-emerald-700" x-text="formatar(descontoTotal)"></strong></div>
                            <div class="pdv-resumo__total">
                                <span>TOTAL GERAL</span>
                                <strong x-text="formatar(total)"></strong>
                            </div>
                        </div>

                        <div class="pdv-card">
                            <div class="pdv-card__label">FORMA DE PAGAMENTO</div>
                            <div class="pdv-pgto__especies">
                                <button type="button" class="pdv-pgto-esp pgto-din" :class="{ 'pdv-pgto-esp--ativa': formaPagamento === 'dinheiro' }" @click="selecionarForma('dinheiro')">
                                    <span>DINHEIRO</span><small>Troco</small>
                                </button>
                                <button type="button" class="pdv-pgto-esp pgto-pix" :class="{ 'pdv-pgto-esp--ativa': formaPagamento === 'pix' }" @click="selecionarForma('pix')">
                                    <span>PIX</span><small>Instantâneo</small>
                                </button>
                                <button type="button" class="pdv-pgto-esp pgto-cred" :class="{ 'pdv-pgto-esp--ativa': formaPagamento === 'cartao_credito' }" @click="selecionarForma('cartao_credito')">
                                    <span>CRÉDITO</span><small>Cartão</small>
                                </button>
                                <button type="button" class="pdv-pgto-esp pgto-deb" :class="{ 'pdv-pgto-esp--ativa': formaPagamento === 'cartao_debito' }" @click="selecionarForma('cartao_debito')">
                                    <span>DÉBITO</span><small>Cartão</small>
                                </button>
                            </div>

                            <div class="pdv-pgto__totais">
                                <div class="pdv-pgto__cel">
                                    <span class="pdv-pgto__cel-lbl">Forma selecionada</span>
                                    <strong x-text="rotuloForma"></strong>
                                </div>
                                <div class="pdv-pgto__cel">
                                    <span class="pdv-pgto__cel-lbl">A pagar</span>
                                    <strong x-text="formatar(total)"></strong>
                                </div>
                                <template x-if="formaPagamento === 'dinheiro'">
                                    <div class="pdv-pgto__cel">
                                        <span class="pdv-pgto__cel-lbl">Valor recebido</span>
                                        <input type="number" min="0" step="0.01" class="pdv-pgto__input" x-model.number="valorRecebido">
                                    </div>
                                </template>
                                <template x-if="formaPagamento === 'dinheiro'">
                                    <div class="pdv-pgto__cel pdv-pgto__cel--troco">
                                        <span class="pdv-pgto__cel-lbl">Troco</span>
                                        <strong x-text="formatar(troco)"></strong>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="pdv-finalizar" :disabled="itens.length === 0 || salvando" @click="finalizar()">
                        <span class="pdv-finalizar__ico">🛒</span>
                        <span class="pdv-finalizar__txt">
                            <strong>FINALIZAR VENDA</strong>
                            <small>F4</small>
                        </span>
                    </button>
                </aside>
            </div>

            <footer class="pdv-footer">
                <span class="pdv-status-badge pdv-status-badge--online"><span class="pdv-dot"></span>PDV ONLINE</span>
                <span class="pdv-footer__sep"></span>
                <span x-text="agora"></span>
                <span>Operador: {{ auth()->user()->name }}</span>
                <span class="pdv-footer__shortcuts">
                    <span><kbd>F2</kbd> Pesquisar</span>
                    <span><kbd>F4</kbd> Finalizar</span>
                    <span><kbd>F6</kbd> Desconto</span>
                    <span><kbd>F7</kbd> Cliente</span>
                    <span><kbd>ESC</kbd> Cancelar</span>
                </span>
            </footer>

            {{-- Modal cliente --}}
            <div class="pdv-modal" x-show="dlgCliente" x-cloak @keydown.escape.window="dlgCliente = false">
                <div class="pdv-modal__card" @click.outside="dlgCliente = false">
                    <h3>Buscar cliente</h3>
                    <input type="text" class="pdv-pgto__input" placeholder="Nome ou CPF..." x-model="clienteTermo" @input.debounce.300ms="buscarCliente()" x-ref="campoCliente">
                    <ul class="pdv-sugestoes mt-2" x-show="clienteResultados.length">
                        <template x-for="c in clienteResultados" :key="c.id">
                            <li class="pdv-sugestao" @click="cliente = c; dlgCliente = false; clienteTermo = ''; clienteResultados = []">
                                <span class="pdv-sugestao__nome" x-text="c.nome"></span>
                                <span class="pdv-sugestao__meta" x-text="c.cpf"></span>
                            </li>
                        </template>
                    </ul>
                    <div class="pdv-modal__actions">
                        <button type="button" class="pdv-btn" @click="dlgCliente = false">Fechar</button>
                    </div>
                </div>
            </div>

            {{-- Modal observação --}}
            <div class="pdv-modal" x-show="dlgObs" x-cloak>
                <div class="pdv-modal__card" @click.outside="dlgObs = false">
                    <h3>Observações</h3>
                    <textarea rows="4" class="pdv-pgto__input" x-model="observacao" placeholder="Observação da venda..."></textarea>
                    <div class="pdv-modal__actions">
                        <button type="button" class="pdv-btn pdv-btn--primary" @click="dlgObs = false">OK</button>
                    </div>
                </div>
            </div>

            {{-- Modal desconto geral --}}
            <div class="pdv-modal" x-show="dlgDesconto" x-cloak>
                <div class="pdv-modal__card" @click.outside="dlgDesconto = false">
                    <h3>Desconto geral (R$)</h3>
                    <p class="mb-2 text-xs text-slate-500">Será aplicado no primeiro item da venda no envio.</p>
                    <input type="number" min="0" step="0.01" class="pdv-pgto__input" x-model.number="descontoGeral">
                    <div class="pdv-modal__actions">
                        <button type="button" class="pdv-btn" @click="descontoGeral = 0; dlgDesconto = false">Limpar</button>
                        <button type="button" class="pdv-btn pdv-btn--primary" @click="dlgDesconto = false">Aplicar</button>
                    </div>
                </div>
            </div>

            {{-- Modal desconto item --}}
            <div class="pdv-modal" x-show="dlgDescontoItem" x-cloak>
                <div class="pdv-modal__card" @click.outside="dlgDescontoItem = false">
                    <h3>Desconto do item</h3>
                    <p class="mb-2 text-sm text-slate-600" x-text="itens[selecionado]?.nome || ''"></p>
                    <input type="number" min="0" step="0.01" class="pdv-pgto__input" x-model.number="descontoItemValor">
                    <div class="pdv-modal__actions">
                        <button type="button" class="pdv-btn" @click="dlgDescontoItem = false">Cancelar</button>
                        <button type="button" class="pdv-btn pdv-btn--primary" @click="confirmarDescontoItem()">OK</button>
                    </div>
                </div>
            </div>

            {{-- Modal troco / confirmar dinheiro --}}
            <div class="pdv-modal" x-show="dlgTroco" x-cloak>
                <div class="pdv-modal__card" @click.outside="dlgTroco = false">
                    <h3>Confirmar pagamento em dinheiro</h3>
                    <div class="pdv-resumo__linha"><span>Total</span><strong x-text="formatar(total)"></strong></div>
                    <div class="mt-2">
                        <span class="pdv-pgto__cel-lbl">Valor recebido</span>
                        <input type="number" min="0" step="0.01" class="pdv-pgto__input" x-model.number="valorRecebido" x-ref="campoTroco">
                    </div>
                    <div class="pdv-resumo__linha mt-2"><span>Troco</span><strong class="text-emerald-700" x-text="formatar(troco)"></strong></div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <template x-for="atalho in atalhosTroco" :key="atalho">
                            <button type="button" class="pdv-btn" @click="valorRecebido = atalho" x-text="formatar(atalho)"></button>
                        </template>
                    </div>
                    <div class="pdv-modal__actions">
                        <button type="button" class="pdv-btn" @click="dlgTroco = false">Voltar</button>
                        <button type="button" class="pdv-btn pdv-btn--primary" :disabled="valorRecebido + 0.001 < total" @click="enviarFormulario()">Confirmar</button>
                    </div>
                </div>
            </div>

            <form id="form-venda" method="POST" action="{{ route('vendas.store') }}" class="hidden">
                @csrf
                <input type="hidden" name="caixa_id" value="{{ $caixaAberto->id }}">
                <input type="hidden" name="cliente_id" x-bind:value="cliente?.id ?? ''">
                <input type="hidden" name="forma_pagamento" x-bind:value="formaPagamento">
                <input type="hidden" name="observacao" x-bind:value="observacaoComTroco">
                <template x-for="(item, indice) in itensParaEnvio" :key="'campo-' + item.chave">
                    <span>
                        <input type="hidden" :name="'itens[' + indice + '][' + (item.tipo === 'produto' ? 'produto_id' : 'tipo_entrada_id') + ']'" :value="item.id">
                        <input type="hidden" :name="'itens[' + indice + '][quantidade]'" :value="item.quantidade">
                        <input type="hidden" :name="'itens[' + indice + '][desconto]'" :value="item.desconto">
                    </span>
                </template>
            </form>
        </div>

        @push('scripts')
            <script>
                document.addEventListener('alpine:init', () => {
                    Alpine.data('pdvApp', () => ({
                        produtos: @json($produtosJson),
                        tiposEntrada: @json($tiposEntradaJson),
                        itens: [],
                        selecionado: null,
                        codigoDigitado: '',
                        mensagemCodigo: '',
                        mostrarSugestoes: false,
                        sugestaoIdx: 0,
                        cliente: null,
                        clienteTermo: '',
                        clienteResultados: [],
                        formaPagamento: 'dinheiro',
                        observacao: '',
                        descontoGeral: 0,
                        descontoItemValor: 0,
                        valorRecebido: 0,
                        proximaChave: 1,
                        salvando: false,
                        ultimoItem: '',
                        agora: '',
                        dlgCliente: false,
                        dlgObs: false,
                        dlgDesconto: false,
                        dlgDescontoItem: false,
                        dlgTroco: false,

                        init() {
                            this.tickAgora();
                            setInterval(() => this.tickAgora(), 1000);
                            this.$nextTick(() => this.$refs.campoCodigo?.focus());
                            @if ($errors->any())
                                alert(@json($errors->first()));
                            @endif
                            @if (session('erro'))
                                alert(@json(session('erro')));
                            @endif
                        },

                        tickAgora() {
                            const d = new Date();
                            this.agora = d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR');
                        },

                        get subtotal() {
                            return this.itens.reduce((s, i) => s + (i.preco * i.quantidade), 0);
                        },
                        get descontoItens() {
                            return this.itens.reduce((s, i) => s + (i.desconto || 0), 0);
                        },
                        get descontoTotal() {
                            return this.descontoItens + Math.max(0, Number(this.descontoGeral) || 0);
                        },
                        get total() {
                            return Math.max(0, this.subtotal - this.descontoTotal);
                        },
                        get troco() {
                            return Math.max(0, (Number(this.valorRecebido) || 0) - this.total);
                        },
                        get rotuloForma() {
                            return ({ dinheiro: 'Dinheiro', pix: 'Pix', cartao_credito: 'Cartão crédito', cartao_debito: 'Cartão débito' })[this.formaPagamento] || this.formaPagamento;
                        },
                        get atalhosTroco() {
                            const base = [this.total, 50, 100, 200].map((v) => Math.ceil(Number(v) * 100) / 100);
                            return [...new Set(base)].filter((v) => v >= this.total).slice(0, 4);
                        },
                        get sugestoesItens() {
                            const termo = this.codigoDigitado.trim().toLowerCase();
                            if (! termo) return [];
                            const entradas = this.tiposEntrada
                                .filter((t) => t.nome.toLowerCase().includes(termo))
                                .map((t) => ({ tipo: 'entrada', id: t.id, nome: t.nome, sku: null, preco: t.preco, controla_estoque: false, estoque: null }));
                            const produtos = this.produtos
                                .filter((p) => (p.sku && p.sku.toLowerCase().includes(termo)) || p.nome.toLowerCase().includes(termo))
                                .map((p) => ({ tipo: 'produto', id: p.id, nome: p.nome, sku: p.sku, preco: p.preco, controla_estoque: p.controla_estoque, estoque: p.estoque }));
                            return [...entradas, ...produtos].slice(0, 8);
                        },
                        get itensParaEnvio() {
                            const itens = this.itens.map((i) => ({ ...i, desconto: Number(i.desconto) || 0 }));
                            let geral = Math.max(0, Number(this.descontoGeral) || 0);
                            if (geral > 0 && itens.length) {
                                const max = itens[0].preco * itens[0].quantidade - itens[0].desconto;
                                itens[0].desconto += Math.min(geral, Math.max(0, max));
                            }
                            return itens;
                        },
                        get observacaoComTroco() {
                            let obs = this.observacao || '';
                            if (this.formaPagamento === 'dinheiro' && this.valorRecebido > 0) {
                                const extra = `Recebido: ${this.formatar(this.valorRecebido)} | Troco: ${this.formatar(this.troco)}`;
                                obs = obs ? `${obs} | ${extra}` : extra;
                            }
                            return obs;
                        },

                        formatar(valor) {
                            return 'R$ ' + Number(valor || 0).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                        },

                        selecionarForma(forma) {
                            this.formaPagamento = forma;
                            if (forma === 'dinheiro' && (! this.valorRecebido || this.valorRecebido < this.total)) {
                                this.valorRecebido = this.total;
                            }
                        },

                        adicionarItem(item) {
                            if (item.tipo === 'produto') {
                                const prod = this.produtos.find((p) => p.id === item.id);
                                if (prod?.controla_estoque) {
                                    const noCarrinho = this.itens.filter((i) => i.tipo === 'produto' && i.id === item.id).reduce((s, i) => s + i.quantidade, 0);
                                    if (noCarrinho + item.quantidade > prod.estoque) {
                                        this.mensagemCodigo = `Estoque insuficiente para "${prod.nome}" (disp.: ${prod.estoque}).`;
                                        return;
                                    }
                                }
                            }
                            const existente = this.itens.find((i) => i.tipo === item.tipo && i.id === item.id);
                            if (existente) {
                                existente.quantidade += item.quantidade;
                                this.selecionado = this.itens.indexOf(existente);
                            } else {
                                item.chave = this.proximaChave++;
                                this.itens.push(item);
                                this.selecionado = this.itens.length - 1;
                            }
                            this.ultimoItem = `${item.quantidade}x ${item.nome}`;
                            this.mensagemCodigo = '';
                        },

                        incluirPorCodigo() {
                            this.mensagemCodigo = '';
                            if (this.sugestoesItens.length && this.sugestaoIdx >= 0 && this.mostrarSugestoes) {
                                const escolhido = this.sugestoesItens[this.sugestaoIdx];
                                if (escolhido) { this.selecionarItemSugerido(escolhido); return; }
                            }
                            const termo = this.codigoDigitado.trim();
                            if (! termo) { this.$refs.campoCodigo?.focus(); return; }
                            const termoLower = termo.toLowerCase();
                            let produto = this.produtos.find((p) => p.sku && p.sku.toLowerCase() === termoLower)
                                || this.produtos.find((p) => String(p.id) === termo);
                            if (! produto) {
                                const porNome = this.produtos.filter((p) => p.nome.toLowerCase().includes(termoLower));
                                if (porNome.length === 1) produto = porNome[0];
                            }
                            if (produto) {
                                this.adicionarItem({ tipo: 'produto', id: produto.id, codigo: produto.sku || ('#' + produto.id), nome: produto.nome, preco: produto.preco, quantidade: 1, desconto: 0 });
                                this.codigoDigitado = '';
                                this.mostrarSugestoes = false;
                                this.sugestaoIdx = 0;
                                this.$refs.campoCodigo?.focus();
                                return;
                            }
                            const entradas = this.tiposEntrada.filter((t) => t.nome.toLowerCase().includes(termoLower));
                            if (entradas.length === 1) {
                                this.incluirEntrada(entradas[0].id, entradas[0].nome, entradas[0].preco);
                                this.codigoDigitado = '';
                                this.mostrarSugestoes = false;
                                this.$refs.campoCodigo?.focus();
                                return;
                            }
                            this.mensagemCodigo = 'Nada encontrado para "' + termo + '".';
                        },

                        selecionarItemSugerido(item) {
                            if (item.tipo === 'entrada') this.incluirEntrada(item.id, item.nome, item.preco);
                            else this.adicionarItem({ tipo: 'produto', id: item.id, codigo: item.sku || ('#' + item.id), nome: item.nome, preco: item.preco, quantidade: 1, desconto: 0 });
                            this.codigoDigitado = '';
                            this.mostrarSugestoes = false;
                            this.sugestaoIdx = 0;
                            this.$refs.campoCodigo?.focus();
                        },

                        incluirEntrada(id, nome, valor) {
                            this.adicionarItem({ tipo: 'entrada', id, codigo: 'ENT-' + id, nome, preco: valor, quantidade: 1, desconto: 0 });
                        },

                        alterarQtd(indice, delta) {
                            const item = this.itens[indice];
                            if (! item) return;
                            const nova = item.quantidade + delta;
                            if (nova <= 0) { this.removerItem(indice); return; }
                            if (item.tipo === 'produto') {
                                const prod = this.produtos.find((p) => p.id === item.id);
                                if (prod?.controla_estoque && nova > prod.estoque) {
                                    this.mensagemCodigo = `Estoque insuficiente (disp.: ${prod.estoque}).`;
                                    return;
                                }
                            }
                            item.quantidade = nova;
                            const bruto = item.preco * item.quantidade;
                            if (item.desconto > bruto) item.desconto = bruto;
                        },

                        removerItem(indice) {
                            this.itens.splice(indice, 1);
                            this.selecionado = this.itens.length ? Math.min(indice, this.itens.length - 1) : null;
                        },

                        aplicarDescontoNoSelecionado() {
                            if (this.selecionado === null || ! this.itens[this.selecionado]) {
                                alert('Selecione um item na lista antes de aplicar desconto.');
                                return;
                            }
                            this.descontoItemValor = this.itens[this.selecionado].desconto || 0;
                            this.dlgDescontoItem = true;
                        },

                        confirmarDescontoItem() {
                            const item = this.itens[this.selecionado];
                            if (! item) { this.dlgDescontoItem = false; return; }
                            const bruto = item.preco * item.quantidade;
                            const numero = Math.max(0, Number(this.descontoItemValor) || 0);
                            item.desconto = Math.min(numero, bruto);
                            this.dlgDescontoItem = false;
                        },

                        abrirCliente() {
                            this.dlgCliente = true;
                            this.$nextTick(() => this.$refs.campoCliente?.focus());
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
                            this.mostrarSugestoes = true;
                        },

                        cancelarVenda() {
                            if (this.itens.length > 0 && ! confirm('Cancelar esta venda e limpar o carrinho?')) return;
                            this.itens = [];
                            this.selecionado = null;
                            this.cliente = null;
                            this.observacao = '';
                            this.descontoGeral = 0;
                            this.codigoDigitado = '';
                            this.mensagemCodigo = '';
                            this.ultimoItem = '';
                            this.valorRecebido = 0;
                            this.iniciarVenda();
                        },

                        moverSugestao(delta) {
                            if (! this.sugestoesItens.length) return;
                            this.mostrarSugestoes = true;
                            const max = this.sugestoesItens.length - 1;
                            this.sugestaoIdx = Math.max(0, Math.min(max, this.sugestaoIdx + delta));
                        },

                        finalizar() {
                            if (this.itens.length === 0 || this.salvando) return;
                            if (this.formaPagamento === 'dinheiro') {
                                if (! this.valorRecebido || this.valorRecebido < this.total) this.valorRecebido = this.total;
                                this.dlgTroco = true;
                                this.$nextTick(() => this.$refs.campoTroco?.focus());
                                return;
                            }
                            this.enviarFormulario();
                        },

                        enviarFormulario() {
                            this.dlgTroco = false;
                            this.salvando = true;
                            this.$nextTick(() => document.getElementById('form-venda').submit());
                        },

                        onKeydown(evento) {
                            const alvo = evento.target;
                            const emCampoTexto = ['INPUT', 'TEXTAREA', 'SELECT'].includes(alvo.tagName);
                            if (evento.key === 'F2') { evento.preventDefault(); this.iniciarVenda(); return; }
                            if (evento.key === 'F4') { evento.preventDefault(); this.finalizar(); return; }
                            if (evento.key === 'F6') { evento.preventDefault(); this.aplicarDescontoNoSelecionado(); return; }
                            if (evento.key === 'F7') { evento.preventDefault(); this.abrirCliente(); return; }
                            if (evento.key === 'Escape') {
                                if (this.dlgCliente || this.dlgObs || this.dlgDesconto || this.dlgDescontoItem || this.dlgTroco) {
                                    this.dlgCliente = this.dlgObs = this.dlgDesconto = this.dlgDescontoItem = this.dlgTroco = false;
                                    return;
                                }
                                evento.preventDefault();
                                this.cancelarVenda();
                                return;
                            }
                            if (evento.key === 'Delete' && ! emCampoTexto && this.selecionado !== null) {
                                evento.preventDefault();
                                this.removerItem(this.selecionado);
                            }
                        },
                    }));
                });
            </script>
        @endpush
    @endif
@endsection
