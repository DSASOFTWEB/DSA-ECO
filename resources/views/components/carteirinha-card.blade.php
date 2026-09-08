@props(['carteirinha'])

@php
    $titular = $carteirinha->titular();
    $ehDependente = $titular instanceof \App\Models\Dependente;
    $clienteBase = $ehDependente ? $titular->cliente : $titular;
    $empresa = $clienteBase?->empresa;

    $contratoAtivo = $ehDependente
        ? $titular?->contratos->firstWhere('status', 'ativo')
        : $titular?->contratoAtivo;

    $fotoUrl = $titular?->foto_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($titular->foto_path) : null;
    $iniciais = collect(explode(' ', trim($titular?->nome ?? '?')))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
@endphp

<div class="mx-auto w-full max-w-[360px] overflow-hidden rounded-2xl bg-gradient-to-br from-slate-800 via-sky-900 to-slate-900 p-5 text-white shadow-lg" style="aspect-ratio: 1.586 / 1;">
    <div class="flex h-full flex-col justify-between">
        <div class="flex items-start justify-between">
            <div class="flex items-center gap-2">
                @if ($empresa?->logoDataUri())
                    <img src="{{ $empresa->logoDataUri() }}" alt="{{ $empresa->nome }}" class="h-7 max-w-[90px] object-contain">
                @else
                    <span class="text-xs font-bold uppercase tracking-wide text-white/90">{{ $empresa?->nome }}</span>
                @endif
            </div>
            <span class="text-[9px] font-semibold uppercase tracking-widest text-white/50">Carteirinha digital</span>
        </div>

        <div class="flex items-center gap-3">
            <div class="grid h-14 w-14 shrink-0 place-items-center overflow-hidden rounded-full border-2 border-white/30 bg-white/10">
                @if ($fotoUrl)
                    <img src="{{ $fotoUrl }}" alt="{{ $titular?->nome }}" class="h-full w-full object-cover">
                @else
                    <span class="text-lg font-bold text-white/80">{{ $iniciais ?: '?' }}</span>
                @endif
            </div>
            <div class="min-w-0">
                <p class="truncate text-base font-bold leading-tight">{{ $titular?->nome ?? 'Titular não encontrado' }}</p>
                <p class="truncate text-xs text-white/60">{{ $ehDependente ? 'Dependente' : 'Titular' }} @if($contratoAtivo) · {{ $contratoAtivo->plano->nome }} @endif</p>
            </div>
        </div>

        <div class="flex items-end justify-between">
            <div>
                <p class="font-mono text-[10px] tracking-wider text-white/50">{{ $carteirinha->codigo }}</p>
                <p class="text-[10px] text-white/50">
                    Validade:
                    {{ $contratoAtivo?->data_fim?->format('m/Y') ?? 'enquanto o plano estiver ativo' }}
                </p>
                <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[9px] font-semibold uppercase {{ $carteirinha->estaAtiva() ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300' }}">{{ $carteirinha->status }}</span>
            </div>
            <div class="rounded-md bg-white p-1">
                <img src="{{ route('carteirinhas.qrcode', $carteirinha) }}" alt="QR Code" class="h-14 w-14">
            </div>
        </div>
    </div>
</div>
