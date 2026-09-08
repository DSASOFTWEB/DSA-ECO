<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Comprar entrada')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        /* Esconde a barra de rolagem do cartão (mas o touch-scroll continua
           funcionando como rede de segurança em telas muito pequenas/muitos
           tipos de entrada) — o layout é dimensionado pra normalmente nem
           precisar disso. */
        .sem-barra-rolagem::-webkit-scrollbar { display: none; }
        .sem-barra-rolagem { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="relative h-dvh overflow-hidden bg-gradient-to-b from-sky-600 via-cyan-500 to-teal-500 text-gray-900 antialiased">
    {{-- Fundo temático "parque aquático": bolhas soltas + duas camadas de onda no rodapé, tudo decorativo (SVG/CSS puro, sem imagem externa) --}}
    <div class="pointer-events-none fixed inset-0 overflow-hidden" aria-hidden="true">
        <div class="absolute -left-10 top-10 h-40 w-40 rounded-full bg-white/10 blur-2xl"></div>
        <div class="absolute right-0 top-1/3 h-56 w-56 rounded-full bg-white/10 blur-3xl"></div>
        <div class="absolute left-1/4 top-2/3 h-24 w-24 rounded-full bg-white/10 blur-xl"></div>
        <div class="absolute -right-8 bottom-24 h-32 w-32 rounded-full bg-white/10 blur-2xl"></div>

        <svg class="absolute bottom-0 left-0 w-full text-white/10" viewBox="0 0 1440 200" preserveAspectRatio="none" fill="currentColor">
            <path d="M0,96 C240,160 480,32 720,64 C960,96 1200,192 1440,128 L1440,320 L0,320 Z"></path>
        </svg>
        <svg class="absolute bottom-0 left-0 w-full text-white/20" viewBox="0 0 1440 200" preserveAspectRatio="none" fill="currentColor">
            <path d="M0,160 C240,96 480,192 720,144 C960,96 1200,64 1440,112 L1440,320 L0,320 Z"></path>
        </svg>
    </div>

    <main class="relative z-10 mx-auto flex h-dvh max-w-lg flex-col px-3 py-2.5">
        <div class="mb-2 flex shrink-0 items-center justify-center gap-2">
            @if (($unidade ?? null)?->empresa?->logoUrl())
                <img src="{{ $unidade->empresa->logoUrl() }}" alt="{{ $unidade->empresa->nome }}" class="h-8 max-w-[160px] object-contain drop-shadow">
            @else
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-white/20 text-white backdrop-blur">
                    <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="1.8" d="M3 16.5c2.5-2 4.5-2 7 0s4.5 2 7 0 3.5-2 4-1.5M4 12c2-1.5 4-1.5 6 0s4 1.5 6 0 3.5-1.5 4-1M6 7.5h12"/></svg>
                </span>
            @endif
            <span class="text-sm font-bold text-white drop-shadow-sm">{{ ($unidade ?? null)?->nome ?? config('app.name', 'Parque Aquático') }}</span>
        </div>

        <div class="shrink-0">@include('partials.flash')</div>

        <div class="sem-barra-rolagem min-h-0 flex-1 overflow-y-auto rounded-3xl border border-white/40 bg-white/95 p-4 shadow-xl backdrop-blur">
            @yield('conteudo')
        </div>
    </main>
</body>
</html>
