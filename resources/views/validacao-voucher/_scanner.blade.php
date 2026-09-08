<div class="max-w-xl rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900"
     x-data="{
        codigo: '',
        validando: false,
        resultado: null,
        scannerAberto: false,
        scannerInstancia: null,
        scannerErro: '',
        validar() {
            const codigo = this.codigo.trim();
            if (!codigo || this.validando) return;
            this.validando = true;
            this.resultado = null;
            fetch('{{ route('validacao-voucher.validar') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify({ codigo }),
            })
                .then(r => r.json())
                .then(dados => { this.resultado = dados; this.validando = false; this.codigo = ''; })
                .catch(() => { this.validando = false; this.resultado = { status: 'erro', mensagem: 'Falha ao validar. Tente novamente.' }; });
        },
        iniciarScanner() {
            this.scannerErro = '';
            this.scannerAberto = true;
            this.$nextTick(() => {
                this.scannerInstancia = new QrScanner(this.$refs.video, (r) => {
                    this.codigo = r.data ?? r;
                    this.validar();
                }, { highlightScanRegion: true, highlightCodeOutline: true, maxScansPerSecond: 5 });

                this.scannerInstancia.start().catch(() => {
                    this.scannerErro = 'Não foi possível acessar a câmera. Verifique a permissão do navegador.';
                    this.scannerAberto = false;
                });
            });
        },
        pararScanner() {
            this.scannerInstancia?.stop();
            this.scannerInstancia?.destroy();
            this.scannerInstancia = null;
            this.scannerAberto = false;
        }
     }"
     x-init="$watch('scannerAberto', (aberto) => { if (!aberto) pararScanner(); })">
    <h2 class="mb-1 text-lg font-semibold text-gray-800 dark:text-white/90">Validar voucher na portaria</h2>
    <p class="mb-4 text-sm text-slate-500">Escaneie o QR do voucher (venda avulsa online ou check-in online) ou digite o código. Cada voucher só passa uma vez.</p>

    <div class="flex gap-2">
        <input type="text" x-model="codigo" @keydown.enter.prevent="validar()" autofocus
               placeholder="Código do voucher (VCH-...)"
               class="flex-1 rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-800 dark:text-white">

        <button type="button" @click="scannerAberto ? pararScanner() : iniciarScanner()"
                class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border px-3.5 py-2.5 text-sm font-medium transition"
                :class="scannerAberto ? 'border-rose-300 text-rose-600 hover:bg-rose-50 dark:border-rose-500/40 dark:text-rose-400' : 'border-brand-300 text-brand-600 hover:bg-brand-50 dark:border-brand-500/40 dark:text-brand-400 dark:hover:bg-brand-500/10'">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M4 8V6a2 2 0 0 1 2-2h2M4 16v2a2 2 0 0 0 2 2h2m8-16h2a2 2 0 0 1 2 2v2m-4 12h2a2 2 0 0 0 2-2v-2M8 12h8"/></svg>
            <span x-text="scannerAberto ? 'Fechar câmera' : 'Escanear QR'"></span>
        </button>

        <button type="button" @click="validar()" :disabled="validando || !codigo.trim()"
                class="shrink-0 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-40">
            Validar
        </button>
    </div>

    <div x-show="scannerAberto" x-cloak class="mt-4 overflow-hidden rounded-xl border border-gray-200 bg-black dark:border-gray-700">
        <video x-ref="video" class="aspect-square w-full object-cover"></video>
    </div>
    <p x-show="scannerErro" x-text="scannerErro" class="mt-2 text-xs text-rose-500"></p>

    <p x-show="validando" class="mt-4 text-sm text-slate-400">Verificando...</p>

    <template x-if="resultado">
        <div class="mt-5 rounded-xl border p-4 text-center"
             :class="{
                'border-emerald-300 bg-emerald-100 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400': resultado.status === 'valido',
                'border-rose-300 bg-rose-100 text-rose-800 dark:border-rose-700 dark:bg-rose-500/15 dark:text-rose-400': resultado.status === 'ja_usado' || resultado.status === 'negado',
                'border-amber-300 bg-amber-100 text-amber-800 dark:border-amber-700 dark:bg-amber-500/15 dark:text-amber-400': resultado.status === 'expirado',
                'border-gray-300 bg-gray-100 text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300': resultado.status === 'invalido' || resultado.status === 'erro',
             }">
            <p class="text-sm font-bold uppercase tracking-wide" x-text="resultado.status === 'valido' ? 'ENTRADA VÁLIDA' : (resultado.status === 'ja_usado' ? 'JÁ UTILIZADO — POSSÍVEL FRAUDE' : (resultado.status === 'negado' ? 'ENTRADA NEGADA' : (resultado.status === 'expirado' ? 'VOUCHER EXPIRADO' : 'CÓDIGO INVÁLIDO')))"></p>
            <p class="mt-1 text-lg font-bold" x-show="resultado.nome" x-text="resultado.nome"></p>
            <p class="mt-1 text-sm" x-text="resultado.mensagem"></p>
            <p class="mt-1 text-xs opacity-70" x-show="resultado.registrado_em" x-text="'Emitido em ' + resultado.registrado_em"></p>
        </div>
    </template>
</div>
