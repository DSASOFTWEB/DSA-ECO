{{--
    Envia um .bin ESC/POS gerado no servidor para a impressora térmica:
    agente local (impressao.agente_url da empresa) → Web Serial → download.
    Uso: imprimirEscPos({ url, agente, colunas, arquivo, status: el, botao: el })
--}}
<script>
async function imprimirEscPos({ url, agente, colunas, arquivo, status, botao }) {
    const avisar = (msg) => { if (status) status.textContent = msg || ''; };
    if (botao) botao.disabled = true;
    try {
        avisar('Gerando impressão ESC/POS...');
        const res = await fetch(url, { credentials: 'same-origin' });
        if (! res.ok) throw new Error('Falha ao gerar ESC/POS (HTTP ' + res.status + ')');
        const bytes = new Uint8Array(await res.arrayBuffer());

        if (agente) {
            let binario = '';
            for (let i = 0; i < bytes.length; i += 0x8000) binario += String.fromCharCode(...bytes.subarray(i, i + 0x8000));
            const ctrl = new AbortController();
            const timer = setTimeout(() => ctrl.abort(), 2500);
            try {
                const r = await fetch(agente.replace(/\/$/, '') + '/print', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ columns: colunas, cut: true, payload_base64: btoa(binario) }),
                    signal: ctrl.signal,
                });
                if (r.ok) { avisar('Enviado à impressora.'); return; }
            } catch (e) { /* agente fora do ar: tenta Web Serial */ } finally { clearTimeout(timer); }
        }

        if ('serial' in navigator) {
            try {
                avisar('Selecione a porta serial da impressora...');
                const port = await navigator.serial.requestPort();
                await port.open({ baudRate: 9600 });
                const writer = port.writable.getWriter();
                try { await writer.write(bytes); } finally { writer.releaseLock(); await port.close(); }
                avisar('Enviado via Web Serial.');
                return;
            } catch (e) { /* cancelou a porta: cai no download */ }
        }

        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([bytes], { type: 'application/octet-stream' }));
        a.download = arquivo;
        a.click();
        URL.revokeObjectURL(a.href);
        avisar('Agente/Serial indisponíveis — arquivo .bin baixado.');
    } catch (e) {
        avisar(e.message || 'Erro ao imprimir ESC/POS');
    } finally {
        if (botao) botao.disabled = false;
    }
}
</script>
