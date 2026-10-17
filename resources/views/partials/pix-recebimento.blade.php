{{--
    Tela do QR Code Pix integrado. $alvo = caminho Alpine do objeto criado
    por pixRecebimento() (ver partials.pix-recebimento-script).
--}}
@php
    $btn = 'rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50';
    $btnOk = 'rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700';
@endphp
<div x-show="{{ $alvo }}.pixAberto" x-cloak class="space-y-3 text-center">
    <template x-if="{{ $alvo }}.pixCarregando">
        <p class="py-8 text-sm text-slate-500">Gerando QR Code no banco...</p>
    </template>
    <template x-if="! {{ $alvo }}.pixCarregando && {{ $alvo }}.pixFalha">
        <div class="space-y-3">
            <p class="rounded-lg bg-rose-50 p-3 text-sm font-semibold text-rose-800" x-text="{{ $alvo }}.pixFalha"></p>
            <div class="flex justify-end gap-2">
                <button type="button" class="{{ $btn }}" @click="{{ $alvo }}.fecharPix()">Voltar</button>
                <button type="button" class="{{ $btnOk }}" @click="{{ $alvo }}.gerarPix()">Tentar novamente</button>
            </div>
        </div>
    </template>
    <template x-if="! {{ $alvo }}.pixCarregando && {{ $alvo }}.pix">
        <div class="space-y-3">
            <p class="text-lg font-bold">R$ <span x-text="Number({{ $alvo }}.pix.valor).toFixed(2).replace('.', ',')"></span></p>
            <template x-if="{{ $alvo }}.pix.status === 'pendente'">
                <div class="flex flex-col items-center gap-2">
                    <img :src="{{ $alvo }}.pix.qr_code" alt="QR Code Pix" width="240" height="240" class="rounded-lg border border-slate-200 bg-white">
                    <div class="flex w-full gap-2">
                        <input type="text" readonly :value="{{ $alvo }}.pix.copia_e_cola" @focus="$event.target.select()" class="w-full rounded-lg border border-slate-300 px-2 py-1 font-mono text-[11px] text-slate-700">
                        <button type="button" class="{{ $btn }}" @click="{{ $alvo }}.copiar()" x-text="{{ $alvo }}.copiado ? 'Copiado!' : 'Copiar'"></button>
                    </div>
                    <p class="text-xs font-semibold text-teal-700">Aguardando pagamento<span x-show="{{ $alvo }}.restante" x-text="' · expira em ' + {{ $alvo }}.restante"></span></p>
                </div>
            </template>
            <template x-if="{{ $alvo }}.pix.status === 'paga'">
                <p class="py-4 text-sm font-semibold text-emerald-700" x-text="{{ $alvo }}.pix.erro ? {{ $alvo }}.pix.erro : 'Pagamento confirmado! Concluindo...'"></p>
            </template>
            <template x-if="{{ $alvo }}.pix.status === 'cancelada' || {{ $alvo }}.pix.status === 'expirada'">
                <p class="rounded-lg bg-rose-50 p-3 text-sm font-semibold text-rose-800" x-text="{{ $alvo }}.pix.status === 'expirada' ? 'O QR Code expirou sem pagamento.' : 'Cobrança cancelada.'"></p>
            </template>
            <p x-show="{{ $alvo }}.pix.aviso" class="text-xs text-amber-700" x-text="{{ $alvo }}.pix.aviso"></p>
            <div class="flex justify-end gap-2">
                <template x-if="{{ $alvo }}.pix.status === 'pendente'">
                    <button type="button" class="{{ $btn }}" :disabled="{{ $alvo }}.cancelando" @click="{{ $alvo }}.cancelarPix()">Cancelar cobrança</button>
                </template>
                <template x-if="{{ $alvo }}.pix.status === 'pendente' && {{ $alvo }}.pix.imprimir_url">
                    <button type="button" class="{{ $btnOk }}" @click="{{ $alvo }}.imprimir()" title="Imprimir o QR Code na impressora 80mm para o cliente">🖨 Imprimir QR Code</button>
                </template>
                <template x-if="{{ $alvo }}.pix.status === 'cancelada' || {{ $alvo }}.pix.status === 'expirada'">
                    <button type="button" class="{{ $btn }}" @click="{{ $alvo }}.fecharPix()">Voltar</button>
                </template>
                <template x-if="{{ $alvo }}.pix.status === 'cancelada' || {{ $alvo }}.pix.status === 'expirada'">
                    <button type="button" class="{{ $btnOk }}" @click="{{ $alvo }}.gerarPix()">Gerar novo QR Code</button>
                </template>
            </div>
        </div>
    </template>
</div>
