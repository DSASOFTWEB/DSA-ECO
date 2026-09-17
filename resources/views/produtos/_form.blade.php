@php
    $p = $produto ?? null;
    $tipoItem = old('tipo_item', $p?->tipo_item ?? 'produto');
@endphp

<div
    class="space-y-8 text-gray-700 dark:text-gray-300 [&_input]:outline-none [&_input]:transition [&_input]:focus:border-brand-500 [&_input]:focus:ring-3 [&_input]:focus:ring-brand-500/10 [&_input]:dark:border-gray-700 [&_input]:dark:bg-gray-800 [&_input]:dark:text-white [&_select]:outline-none [&_select]:transition [&_select]:focus:border-brand-500 [&_select]:focus:ring-3 [&_select]:focus:ring-brand-500/10 [&_select]:dark:border-gray-700 [&_select]:dark:bg-gray-800 [&_select]:dark:text-white [&_textarea]:outline-none [&_textarea]:transition [&_textarea]:focus:border-brand-500 [&_textarea]:focus:ring-3 [&_textarea]:focus:ring-brand-500/10 [&_textarea]:dark:border-gray-700 [&_textarea]:dark:bg-gray-800 [&_textarea]:dark:text-white"
    x-data="produtoFiscalForm({
        tipo: @js($tipoItem),
        ean: @js(old('ean', $p?->ean)),
        nome: @js(old('nome', $p?->nome)),
        ncm: @js(old('ncm', $p?->ncm)),
        imagemUrl: @js(old('imagem_url', $p?->imagem_url)),
        unidade: @js(old('unidade_comercial', $p?->unidade_comercial ?? 'UN')),
        cosmosUrl: @js(route('produtos.consultar-ean')),
        ncmAutocompleteUrl: @js(route('produtos.ncm-autocomplete')),
    })"
>
    {{-- Identificação --}}
    <section class="space-y-4">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Identificação</h3>
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">Tipo do item</label>
                <div class="mt-2 flex flex-wrap gap-4">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="radio" name="tipo_item" value="produto" x-model="tipo" class="border-slate-300 text-brand-600">
                        Produto (NFC-e)
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="radio" name="tipo_item" value="servico" x-model="tipo" class="border-slate-300 text-brand-600">
                        Serviço (NFS-e Nacional)
                    </label>
                </div>
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">Nome</label>
                <input type="text" name="nome" x-model="nome" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Categoria</label>
                <select name="categoria_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">—</option>
                    @foreach ($categorias as $categoria)
                        <option value="{{ $categoria->id }}" @selected(old('categoria_id', $p?->categoria_id) == $categoria->id)>{{ $categoria->nome }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">SKU</label>
                <input type="text" name="sku" value="{{ old('sku', $p?->sku) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">EAN / GTIN</label>
                <div class="mt-1 flex gap-2">
                    <input type="text" name="ean" x-model="ean" maxlength="14" inputmode="numeric" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Código de barras">
                    <button
                        type="button"
                        x-show="tipo === 'produto'"
                        @click="consultarCosmos()"
                        :disabled="consultando"
                        class="shrink-0 rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium hover:bg-slate-50 disabled:opacity-50"
                    >
                        <span x-text="consultando ? 'Buscando…' : 'Cosmos'"></span>
                    </button>
                </div>
                <p class="mt-1 text-xs text-slate-400" x-text="cosmosMsg"></p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Unidade comercial</label>
                <input type="text" name="unidade_comercial" x-model="unidade" maxlength="6" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="UN">
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">URL da imagem</label>
                <div class="mt-1 flex flex-col gap-3 sm:flex-row sm:items-start">
                    <input type="url" name="imagem_url" x-model="imagemUrl" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="https://…">
                    <template x-if="imagemUrl">
                        <img :src="imagemUrl" alt="Prévia" class="h-20 w-20 rounded-lg border border-slate-200 object-contain bg-white">
                    </template>
                </div>
                <p class="mt-1 text-xs text-slate-400">Preenchida automaticamente pela consulta Cosmos quando houver thumbnail.</p>
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">Descrição</label>
                <textarea name="descricao" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('descricao', $p?->descricao) }}</textarea>
            </div>
        </div>
    </section>

    {{-- Comercial --}}
    <section class="space-y-4">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Comercial e estoque</h3>
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700">Preço de custo (R$)</label>
                <input type="number" step="0.01" min="0" name="preco_custo" value="{{ old('preco_custo', $p?->preco_custo ?? 0) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Preço de venda (R$)</label>
                <input type="number" step="0.01" min="0" name="preco_venda" value="{{ old('preco_venda', $p?->preco_venda) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            @unless ($p)
                <div>
                    <label class="block text-sm font-medium text-slate-700">Estoque inicial</label>
                    <input type="number" min="0" name="estoque_atual" value="{{ old('estoque_atual', 0) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            @endunless
            <div>
                <label class="block text-sm font-medium text-slate-700">Estoque mínimo</label>
                <input type="number" min="0" name="estoque_minimo" value="{{ old('estoque_minimo', $p?->estoque_minimo ?? 0) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" id="controla_estoque" name="controla_estoque" value="1" @checked(old('controla_estoque', $p?->controla_estoque ?? true)) class="rounded border-slate-300">
                <label for="controla_estoque" class="text-sm text-slate-700">Controla estoque</label>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" id="ativo" name="ativo" value="1" @checked(old('ativo', $p?->ativo ?? true)) class="rounded border-slate-300">
                <label for="ativo" class="text-sm text-slate-700">Item ativo</label>
            </div>
        </div>
    </section>

    {{-- NFC-e --}}
    @php
        $empresaFiscal = $empresa ?? auth()->user()?->empresa;
        $classificacaoIcms = $classificacaoIcms ?? \App\Support\FiscalTabelas::classificacaoIcmsParaRegime($empresaFiscal?->regime_tributario);
        $campoIcms = $classificacaoIcms['campo'];
        $opcoesIcms = $classificacaoIcms['opcoes'];
        $rotuloIcms = $classificacaoIcms['rotulo'];
        $rotuloRegime = \App\Models\Empresa::regimesTributarios()[$empresaFiscal?->regime_tributario ?? \App\Models\Empresa::REGIME_SIMPLES] ?? 'Simples Nacional';
    @endphp
    <section class="space-y-4" x-show="tipo === 'produto'" x-cloak>
        <div class="flex flex-wrap items-end justify-between gap-2">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Fiscal NFC-e (mercadoria)</h3>
            <p class="text-xs text-slate-400">
                Classificação pré-definida para
                <strong>{{ $rotuloRegime }}</strong>
            </p>
        </div>
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
            <div class="relative">
                <label class="block text-sm font-medium text-slate-700">NCM</label>
                <input
                    type="text"
                    name="ncm"
                    x-model="ncm"
                    @input.debounce.300ms="buscarNcm()"
                    @focus="buscarNcm()"
                    @keydown.escape="ncmSugestoes = []"
                    maxlength="8"
                    inputmode="numeric"
                    autocomplete="off"
                    placeholder="8 dígitos ou descrição"
                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                >
                <p class="mt-1 truncate text-xs text-slate-400" x-show="ncmDescricao" x-text="ncmDescricao"></p>
                <div
                    x-show="ncmSugestoes.length > 0"
                    x-cloak
                    class="absolute z-20 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-900"
                >
                    <template x-for="item in ncmSugestoes" :key="item.ncm">
                        <button
                            type="button"
                            class="block w-full px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-white/5"
                            @click="selecionarNcm(item)"
                        >
                            <span class="font-medium" x-text="item.ncm"></span>
                            <span class="text-slate-500" x-text="' — ' + item.descricao"></span>
                        </button>
                    </template>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">CEST</label>
                <input type="text" name="cest" value="{{ old('cest', $p?->cest) }}" maxlength="7" inputmode="numeric" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">CFOP</label>
                <input type="text" name="cfop" value="{{ old('cfop', $p?->cfop) }}" maxlength="4" inputmode="numeric" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="5102">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Origem da mercadoria</label>
                <select name="origem" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">—</option>
                    @foreach (\App\Support\FiscalTabelas::origemMercadoria() as $origemValor => $origemLabel)
                        <option value="{{ $origemValor }}" @selected((string) old('origem', $p?->origem) === (string) $origemValor)>{{ $origemLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">{{ $rotuloIcms }}</label>
                <select name="{{ $campoIcms }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">—</option>
                    @foreach ($opcoesIcms as $cod => $label)
                        <option value="{{ $cod }}" @selected(old($campoIcms, $p?->{$campoIcms}) == $cod)>{{ $label }}</option>
                    @endforeach
                </select>
                @if ($campoIcms === 'csosn')
                    <input type="hidden" name="cst_icms" value="">
                @else
                    <input type="hidden" name="csosn" value="">
                @endif
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Alíq. ICMS (%)</label>
                <input type="number" step="0.0001" min="0" max="100" name="aliq_icms" value="{{ old('aliq_icms', $p?->aliq_icms) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div class="sm:col-span-3 border-t border-slate-100 pt-4">
                <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">PIS / COFINS — saída (venda)</p>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-4">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700">CST PIS saída</label>
                        <select name="cst_pis" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">—</option>
                            @foreach (\App\Support\FiscalTabelas::cstPisCofinsSaida() as $cod => $label)
                                <option value="{{ $cod }}" @selected(old('cst_pis', $p?->cst_pis) == $cod)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Alíq. PIS saída (%)</label>
                        <input type="number" step="0.0001" min="0" max="100" name="aliq_pis" value="{{ old('aliq_pis', $p?->aliq_pis) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div></div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700">CST COFINS saída</label>
                        <select name="cst_cofins" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">—</option>
                            @foreach (\App\Support\FiscalTabelas::cstPisCofinsSaida() as $cod => $label)
                                <option value="{{ $cod }}" @selected(old('cst_cofins', $p?->cst_cofins) == $cod)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Alíq. COFINS saída (%)</label>
                        <input type="number" step="0.0001" min="0" max="100" name="aliq_cofins" value="{{ old('aliq_cofins', $p?->aliq_cofins) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                </div>
            </div>

            <div class="sm:col-span-3 border-t border-slate-100 pt-4">
                <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">PIS / COFINS — entrada (compra)</p>
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-4">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700">CST PIS entrada</label>
                        <select name="cst_pis_entrada" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">—</option>
                            @foreach (\App\Support\FiscalTabelas::cstPisCofinsEntrada() as $cod => $label)
                                <option value="{{ $cod }}" @selected(old('cst_pis_entrada', $p?->cst_pis_entrada) == $cod)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Alíq. PIS entrada (%)</label>
                        <input type="number" step="0.0001" min="0" max="100" name="aliq_pis_entrada" value="{{ old('aliq_pis_entrada', $p?->aliq_pis_entrada) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div></div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700">CST COFINS entrada</label>
                        <select name="cst_cofins_entrada" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">—</option>
                            @foreach (\App\Support\FiscalTabelas::cstPisCofinsEntrada() as $cod => $label)
                                <option value="{{ $cod }}" @selected(old('cst_cofins_entrada', $p?->cst_cofins_entrada) == $cod)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">Alíq. COFINS entrada (%)</label>
                        <input type="number" step="0.0001" min="0" max="100" name="aliq_cofins_entrada" value="{{ old('aliq_cofins_entrada', $p?->aliq_cofins_entrada) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">CST IPI</label>
                <input type="text" name="cst_ipi" value="{{ old('cst_ipi', $p?->cst_ipi) }}" maxlength="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Alíq. IPI (%)</label>
                <input type="number" step="0.0001" min="0" max="100" name="aliq_ipi" value="{{ old('aliq_ipi', $p?->aliq_ipi) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Cód. benefício fiscal</label>
                <input type="text" name="cod_beneficio" value="{{ old('cod_beneficio', $p?->cod_beneficio) }}" maxlength="10" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
        </div>
    </section>

    {{-- NFS-e Nacional --}}
    <section class="space-y-4" x-show="tipo === 'servico'" x-cloak>
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Fiscal NFS-e Nacional (serviço)</h3>
        <p class="text-xs text-slate-400">Esses valores montam o bloco <code class="rounded bg-slate-100 px-1 dark:bg-gray-800">&lt;cServ&gt;</code> da DPS. A descrição do item vira <code class="rounded bg-slate-100 px-1 dark:bg-gray-800">&lt;xDescServ&gt;</code>.</p>
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
            <div>
                <label class="block text-sm font-medium text-slate-700">cTribNac — LC 116</label>
                <input type="text" name="codigo_servico_lc116" value="{{ old('codigo_servico_lc116', $p?->codigo_servico_lc116) }}" maxlength="10" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="08.02.01">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">cTribMun — trib. municipal</label>
                <input type="text" name="codigo_tributacao_municipal" value="{{ old('codigo_tributacao_municipal', $p?->codigo_tributacao_municipal) }}" maxlength="20" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="010">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">CNAE serviço</label>
                <input type="text" name="cnae_servico" value="{{ old('cnae_servico', $p?->cnae_servico) }}" maxlength="7" inputmode="numeric" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">NBS</label>
                <input type="text" name="nbs" value="{{ old('nbs', $p?->nbs) }}" maxlength="9" inputmode="numeric" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Alíq. ISS (%)</label>
                <input type="number" step="0.0001" min="0" max="100" name="aliq_iss" value="{{ old('aliq_iss', $p?->aliq_iss) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="flex items-end">
                <label class="inline-flex items-center gap-2 pb-2 text-sm text-slate-700">
                    <input type="checkbox" name="iss_retido" value="1" @checked(old('iss_retido', $p?->iss_retido)) class="rounded border-slate-300">
                    ISS retido
                </label>
            </div>
        </div>
    </section>
</div>

@push('scripts')
<script>
function produtoFiscalForm(cfg) {
    return {
        tipo: cfg.tipo || 'produto',
        ean: cfg.ean || '',
        nome: cfg.nome || '',
        ncm: cfg.ncm || '',
        ncmDescricao: '',
        ncmSugestoes: [],
        imagemUrl: cfg.imagemUrl || '',
        unidade: cfg.unidade || 'UN',
        cosmosUrl: cfg.cosmosUrl,
        ncmAutocompleteUrl: cfg.ncmAutocompleteUrl,
        consultando: false,
        cosmosMsg: '',
        async buscarNcm() {
            const busca = String(this.ncm || '').trim();
            this.ncmSugestoes = [];
            if (busca.length < 2 || !this.ncmAutocompleteUrl) {
                return;
            }
            try {
                const res = await fetch(`${this.ncmAutocompleteUrl}?busca=${encodeURIComponent(busca)}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!res.ok) return;
                this.ncmSugestoes = await res.json();
            } catch (e) {
                this.ncmSugestoes = [];
            }
        },
        selecionarNcm(item) {
            this.ncm = item.ncm;
            this.ncmDescricao = item.descricao || '';
            this.ncmSugestoes = [];
        },
        async consultarCosmos() {
            this.cosmosMsg = '';
            const ean = String(this.ean || '').replace(/\D+/g, '');
            if (ean.length < 8) {
                this.cosmosMsg = 'Informe um EAN com pelo menos 8 dígitos.';
                return;
            }
            this.consultando = true;
            try {
                const res = await fetch(`${this.cosmosUrl}?ean=${encodeURIComponent(ean)}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const data = await res.json();
                if (!res.ok) {
                    this.cosmosMsg = data.message || 'Não foi possível consultar o EAN.';
                    return;
                }
                if (data.nome) this.nome = data.nome;
                if (data.ncm) this.ncm = data.ncm;
                if (data.imagem_url) this.imagemUrl = data.imagem_url;
                if (data.unidade_comercial) this.unidade = data.unidade_comercial;
                if (data.ean) this.ean = data.ean;
                this.cosmosMsg = data.marca ? `Encontrado (${data.marca}).` : 'Produto encontrado no Cosmos.';
            } catch (e) {
                this.cosmosMsg = 'Falha de rede ao consultar o Cosmos.';
            } finally {
                this.consultando = false;
            }
        },
    };
}
</script>
@endpush
