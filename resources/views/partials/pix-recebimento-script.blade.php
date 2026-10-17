{{--
    Recebimento Pix integrado (QR Code do gateway da empresa) usado no
    fechamento de mesa/comanda e no check-out da hospedagem. Gera a cobrança
    enviando o formulário da tela, faz polling no status e redireciona quando
    o servidor conclui (paga + conta fechada). Markup: partials.pix-recebimento.
--}}
<script>
function pixRecebimento({ url, formId }) {
    return {
        pixAberto: false,
        pixCarregando: false,
        pixFalha: '',
        pix: null,
        cancelando: false,
        copiado: false,
        restante: '',
        timer: null,
        async gerarPix() {
            this.pararPolling();
            this.pixAberto = true;
            this.pixCarregando = true;
            this.pixFalha = '';
            this.pix = null;
            try {
                const resposta = await fetch(url, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: new FormData(document.getElementById(formId)),
                });
                const dados = await resposta.json().catch(() => ({}));
                if (! resposta.ok) {
                    this.pixFalha = dados.message || Object.values(dados.errors || {})[0]?.[0] || 'Não foi possível gerar o QR Code Pix.';
                    return;
                }
                this.aplicar(dados);
                if (this.pix?.status === 'pendente') this.iniciarPolling();
            } catch (e) {
                this.pixFalha = 'Sem comunicação com o servidor. Verifique a conexão e tente novamente.';
            } finally {
                this.pixCarregando = false;
            }
        },
        iniciarPolling() {
            this.contar();
            this.timer = setInterval(() => { this.contar(); this.consultar(); }, 3000);
        },
        pararPolling() {
            if (this.timer) clearInterval(this.timer);
            this.timer = null;
        },
        contar() {
            if (! this.pix?.expira_em) { this.restante = ''; return; }
            const seg = Math.max(0, Math.floor((new Date(this.pix.expira_em) - new Date()) / 1000));
            this.restante = Math.floor(seg / 60) + ':' + String(seg % 60).padStart(2, '0');
        },
        async consultar() {
            if (! this.pix?.status_url) return;
            try {
                const resposta = await fetch(this.pix.status_url, { headers: { 'Accept': 'application/json' } });
                if (resposta.ok) this.aplicar(await resposta.json());
            } catch (e) { /* tenta de novo no próximo ciclo */ }
        },
        aplicar(dados) {
            this.pix = { ...(this.pix || {}), ...dados, aviso: dados.aviso || '' };
            if (dados.redirect) {
                this.pararPolling();
                window.location.href = dados.redirect;
                return;
            }
            if (['cancelada', 'expirada'].includes(dados.status)) this.pararPolling();
        },
        async cancelarPix() {
            if (! this.pix?.cancelar_url || ! confirm('Cancelar esta cobrança Pix?')) return;
            this.cancelando = true;
            try {
                const resposta = await fetch(this.pix.cancelar_url, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                });
                const dados = await resposta.json().catch(() => ({}));
                if (! resposta.ok) { alert(dados.message || 'Não foi possível cancelar a cobrança.'); return; }
                this.aplicar(dados);
            } finally {
                this.cancelando = false;
            }
        },
        copiar() {
            navigator.clipboard?.writeText(this.pix?.copia_e_cola || '').then(() => {
                this.copiado = true;
                setTimeout(() => { this.copiado = false; }, 1500);
            });
        },
        fecharPix() {
            this.pararPolling();
            this.pixAberto = false;
            this.pix = null;
            this.pixFalha = '';
        },
    };
}
</script>
