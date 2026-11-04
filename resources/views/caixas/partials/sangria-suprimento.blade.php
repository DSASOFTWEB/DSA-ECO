{{--
    Janela de sangria/suprimento (tela do caixa e PDV). Abre com o evento
    "abrir-sangria" (detail: 'sangria' | 'suprimento'). Sempre em dinheiro;
    mostra o dinheiro na gaveta e, ao confirmar, abre o comprovante 80mm.
    Parâmetros: $caixa, $recarregar (bool — recarrega a página depois).
--}}
<div x-data="sangriaSuprimento(@js([
        'urlSangria' => route('caixas.sangria', $caixa),
        'urlSuprimento' => route('caixas.suprimento', $caixa),
        'urlGaveta' => route('caixas.gaveta', $caixa),
        'recarregar' => (bool) ($recarregar ?? false),
    ]))"
     @abrir-sangria.window="abrir($event.detail)"
     @keydown.escape.window="aberto && fechar()"
     x-show="aberto" x-cloak
     style="position: fixed; inset: 0; z-index: 95; background: rgba(15,23,42,.5);">
    {{-- Centralização num filho: o x-show troca o display do elemento de fora. --}}
    <div @click.self="fechar()" style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; padding: 16px;">
    <form @submit.prevent="enviar()" class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-xl dark:bg-gray-900" style="max-width: 400px">
        <div class="mb-3 grid grid-cols-2 gap-2">
            <button type="button" @click="trocar('sangria')" class="rounded-lg border px-3 py-2 text-sm font-bold"
                    :class="tipo === 'sangria' ? 'border-rose-600 bg-rose-600 text-white' : 'border-slate-300 text-slate-600 dark:border-gray-700 dark:text-gray-300'">− Sangria</button>
            <button type="button" @click="trocar('suprimento')" class="rounded-lg border px-3 py-2 text-sm font-bold"
                    :class="tipo === 'suprimento' ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-slate-300 text-slate-600 dark:border-gray-700 dark:text-gray-300'">+ Suprimento</button>
        </div>
        <p class="text-xs text-slate-500" x-text="tipo === 'sangria' ? 'Retirada de dinheiro da gaveta (ex.: recolher ao cofre).' : 'Dinheiro colocado na gaveta (ex.: reforço de troco).'"></p>

        <div class="mt-3 flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-white/[0.04]">
            <span class="text-slate-500">Dinheiro na gaveta</span>
            <strong class="text-slate-800 dark:text-white" x-text="gaveta === null ? '...' : formatar(gaveta)"></strong>
        </div>

        <label class="mt-3 block text-sm font-medium text-slate-700 dark:text-gray-200">Valor (R$)</label>
        <input type="number" step="0.01" min="0.01" x-model.number="valor" x-ref="valor" required
               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-lg font-bold dark:border-gray-700 dark:bg-gray-800 dark:text-white">
        <p class="mt-1 text-xs text-slate-400" x-show="tipo === 'sangria' && gaveta !== null && valor > 0">
            Fica na gaveta: <span x-text="formatar(Math.max(0, gaveta - valor))"></span>
        </p>

        <label class="mt-3 block text-sm font-medium text-slate-700 dark:text-gray-200">
            Motivo <span class="font-normal text-slate-400" x-text="tipo === 'sangria' ? '(obrigatório)' : '(opcional)'"></span>
        </label>
        <input type="text" maxlength="255" x-model="motivo" :required="tipo === 'sangria'"
               :placeholder="tipo === 'sangria' ? 'Recolhimento para o cofre' : 'Reforço de troco'"
               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">

        <p x-show="erro" x-text="erro" class="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700"></p>

        <div class="mt-4 flex justify-end gap-2">
            <button type="button" @click="fechar()" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-gray-300 dark:hover:bg-white/5">Cancelar</button>
            <button type="submit" :disabled="enviando" class="rounded-lg px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                    :class="tipo === 'sangria' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-emerald-600 hover:bg-emerald-700'"
                    x-text="enviando ? 'Registrando...' : 'Confirmar e imprimir'"></button>
        </div>
    </form>
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('sangriaSuprimento', (cfg) => ({
                    aberto: false, tipo: 'sangria', valor: null, motivo: '', gaveta: null, erro: '', enviando: false,

                    async abrir(tipo) {
                        this.tipo = tipo === 'suprimento' ? 'suprimento' : 'sangria';
                        this.valor = null; this.motivo = ''; this.erro = ''; this.aberto = true;
                        document.body.dataset.sangriaAberta = '1';
                        this.$nextTick(() => this.$refs.valor?.focus());
                        this.gaveta = null;
                        try {
                            const r = await fetch(cfg.urlGaveta, { headers: { Accept: 'application/json' } });
                            this.gaveta = (await r.json()).dinheiro;
                        } catch (e) { /* mostra "..." e o servidor valida */ }
                    },
                    trocar(tipo) { this.tipo = tipo; this.erro = ''; this.$refs.valor?.focus(); },
                    fechar() { this.aberto = false; delete document.body.dataset.sangriaAberta; },
                    formatar(v) { return 'R$ ' + Number(v || 0).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); },

                    async enviar() {
                        if (this.enviando) return;
                        if (this.tipo === 'sangria' && this.gaveta !== null && this.valor > this.gaveta + 0.004) {
                            this.erro = 'Sangria maior que o dinheiro na gaveta (' + this.formatar(this.gaveta) + ').';
                            return;
                        }
                        this.enviando = true; this.erro = '';
                        // Aberta já no clique: o navegador não bloqueia a janela do comprovante.
                        const janela = window.open('', 'comprovante-caixa', 'width=420,height=720');
                        try {
                            const r = await fetch(this.tipo === 'sangria' ? cfg.urlSangria : cfg.urlSuprimento, {
                                method: 'POST',
                                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                                body: JSON.stringify({ valor: this.valor, motivo: this.motivo }),
                            });
                            const d = await r.json().catch(() => ({}));
                            if (! r.ok) {
                                janela?.close();
                                this.erro = d.message || 'Não foi possível registrar.';
                                return;
                            }
                            if (janela) janela.location.href = d.comprovante_url;
                            this.gaveta = d.dinheiro;
                            this.fechar();
                            window.dispatchEvent(new CustomEvent('caixa-movimentado', { detail: d }));
                            if (cfg.recarregar) window.location.reload();
                        } catch (e) {
                            janela?.close();
                            this.erro = 'Sem comunicação com o servidor. Tente de novo.';
                        } finally {
                            this.enviando = false;
                        }
                    },
                }));
            });
        </script>
    @endpush
@endonce
