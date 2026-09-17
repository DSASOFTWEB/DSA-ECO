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
    <section class="space-y-4" x-show="tipo === 'produto'" x-cloak>
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Fiscal NFC-e (mercadoria)</h3>
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
            <div>
                <label class="block text-sm font-medium text-slate-700">NCM</label>
                <input type="text" name="ncm" x-model="ncm" maxlength="8" inputmode="numeric" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
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
                <label class="block text-sm font-medium text-slate-700">Origem ICMS</label>
                <select name="origem" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">—</option>
                    @foreach ([
                        0 => '0 — Nacional',
                        1 => '1 — Estrangeira (importação direta)',
                        2 => '2 — Estrangeira (mercado interno)',
                        3 => '3 — Nacional c/ conteúdo importação >40%',
                        4 => '4 — Nacional produção conforme PPB',
                        5 => '5 — Nacional c/ conteúdo importação ≤40%',
                        6 => '6 — Estrangeira (importação direta sem similar)',
                        7 => '7 — Estrangeira (mercado interno sem similar)',
                        8 => '8 — Nacional c/ conteúdo importação >70%',
                    ] as $origemValor => $origemLabel)
                        <option value="{{ $origemValor }}" @selected(old('origem', $p?->origem) === $origemValor || old('origem', $p?->origem) === (string) $origemValor)>{{ $origemLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">CST ICMS</label>
                <input type="text" name="cst_icms" value="{{ old('cst_icms', $p?->cst_icms) }}" maxlength="3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="000">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">CSOSN (Simples)</label>
                <input type="text" name="csosn" value="{{ old('csosn', $p?->csosn) }}" maxlength="4" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="102">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Alíq. ICMS (%)</label>
                <input type="number" step="0.0001" min="0" max="100" name="aliq_icms" value="{{ old('aliq_icms', $p?->aliq_icms) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">CST PIS</label>
                <input type="text" name="cst_pis" value="{{ old('cst_pis', $p?->cst_pis) }}" maxlength="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Alíq. PIS (%)</label>
                <input type="number" step="0.0001" min="0" max="100" name="aliq_pis" value="{{ old('aliq_pis', $p?->aliq_pis) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">CST COFINS</label>
                <input type="text" name="cst_cofins" value="{{ old('cst_cofins', $p?->cst_cofins) }}" maxlength="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Alíq. COFINS (%)</label>
                <input type="number" step="0.0001" min="0" max="100" name="aliq_cofins" value="{{ old('aliq_cofins', $p?->aliq_cofins) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
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
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
            <div>
                <label class="block text-sm font-medium text-slate-700">Código LC 116</label>
                <input type="text" name="codigo_servico_lc116" value="{{ old('codigo_servico_lc116', $p?->codigo_servico_lc116) }}" maxlength="10" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="1.05">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Cód. trib. municipal</label>
                <input type="text" name="codigo_tributacao_municipal" value="{{ old('codigo_tributacao_municipal', $p?->codigo_tributacao_municipal) }}" maxlength="20" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
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
        imagemUrl: cfg.imagemUrl || '',
        unidade: cfg.unidade || 'UN',
        cosmosUrl: cfg.cosmosUrl,
        consultando: false,
        cosmosMsg: '',
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
