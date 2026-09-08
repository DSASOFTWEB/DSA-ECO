@extends('layouts.app')

@section('titulo', 'Check-in (cliente com plano)')

@section('conteudo')
    <div class="max-w-xl rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900"
         x-data="{
            termo: '',
            resultados: [],
            buscando: false,
            scannerAberto: false,
            scannerInstancia: null,
            scannerErro: '',
            buscar() {
                if (this.termo.trim().length < 2) { this.resultados = []; return; }
                this.buscando = true;
                fetch('{{ route('checkin.buscar') }}?termo=' + encodeURIComponent(this.termo))
                    .then(r => r.json())
                    .then(dados => { this.resultados = dados; this.buscando = false; })
                    .catch(() => { this.buscando = false; });
            },
            // Usado tanto pelo Enter do leitor de código de barras (que
            // 'digita' o código + Enter, como um teclado) quanto pela leitura
            // via câmera: um código de carteirinha resolve para exatamente um
            // cliente, então já manda direto pra tela dele em vez de listar.
            buscarEIrDireto() {
                const termo = this.termo.trim();
                if (termo.length < 2) return;
                this.buscando = true;
                fetch('{{ route('checkin.buscar') }}?termo=' + encodeURIComponent(termo))
                    .then(r => r.json())
                    .then(dados => {
                        this.buscando = false;
                        if (dados.length === 1) {
                            window.location.href = '{{ url('checkin') }}/' + dados[0].id;
                        } else {
                            this.resultados = dados;
                        }
                    })
                    .catch(() => { this.buscando = false; });
            },
            iniciarScanner() {
                this.scannerErro = '';
                this.scannerAberto = true;
                this.$nextTick(() => {
                    this.scannerInstancia = new QrScanner(this.$refs.video, (resultado) => {
                        this.termo = resultado.data ?? resultado;
                        this.pararScanner();
                        this.buscarEIrDireto();
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
        <h2 class="mb-1 text-lg font-semibold text-gray-800 dark:text-white/90">Buscar cliente</h2>
        <p class="mb-4 text-sm text-slate-500">Digite CPF, nome, código do cliente, ou escaneie a carteirinha (câmera ou leitor de código de barras).</p>

        <div class="flex gap-2">
            <input type="text" x-model="termo" @input.debounce.300ms="buscar()" @keydown.enter.prevent="buscarEIrDireto()" autofocus
                   placeholder="CPF, nome, código ou cartão..."
                   class="flex-1 rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-800 dark:text-white">

            <button type="button" @click="scannerAberto ? pararScanner() : iniciarScanner()"
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border px-3.5 py-2.5 text-sm font-medium transition"
                    :class="scannerAberto ? 'border-rose-300 text-rose-600 hover:bg-rose-50 dark:border-rose-500/40 dark:text-rose-400' : 'border-brand-300 text-brand-600 hover:bg-brand-50 dark:border-brand-500/40 dark:text-brand-400 dark:hover:bg-brand-500/10'">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M4 8V6a2 2 0 0 1 2-2h2M4 16v2a2 2 0 0 0 2 2h2m8-16h2a2 2 0 0 1 2 2v2m-4 12h2a2 2 0 0 0 2-2v-2M8 12h8"/></svg>
                <span x-text="scannerAberto ? 'Fechar câmera' : 'Escanear QR'"></span>
            </button>
        </div>

        <div x-show="scannerAberto" x-cloak class="mt-4 overflow-hidden rounded-xl border border-gray-200 bg-black dark:border-gray-700">
            <video x-ref="video" class="aspect-square w-full object-cover"></video>
        </div>
        <p x-show="scannerErro" x-text="scannerErro" class="mt-2 text-xs text-rose-500"></p>
        <p x-show="scannerAberto" class="mt-2 text-xs text-slate-400">Aponte a câmera para o QR Code da carteirinha do cliente.</p>

        <p x-show="buscando" class="mt-3 text-sm text-slate-400">Buscando...</p>

        <ul class="mt-3 divide-y divide-slate-100 dark:divide-gray-800" x-show="!buscando && resultados.length">
            <template x-for="cliente in resultados" :key="cliente.id">
                <li>
                    <a :href="'{{ url('checkin') }}/' + cliente.id" class="flex items-center justify-between rounded-lg px-2 py-3 text-sm transition hover:bg-gray-50 dark:hover:bg-white/[0.03]">
                        <span class="font-medium text-gray-800 dark:text-white/90" x-text="cliente.nome"></span>
                        <span class="text-slate-400" x-text="cliente.cpf"></span>
                    </a>
                </li>
            </template>
        </ul>

        <p x-show="!buscando && termo.trim().length >= 2 && !resultados.length" class="mt-3 text-sm text-slate-400">Nenhum cliente encontrado.</p>
    </div>
@endsection
