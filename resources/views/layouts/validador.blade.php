<!DOCTYPE html>
<html lang="pt-BR" class="h-full" x-data="{ darkMode: localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && matchMedia('(prefers-color-scheme: dark)').matches) }" :class="{ 'dark': darkMode }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>App Validador · {{ $empresaAtual->nome ?? config('app.name', 'Parque Aquático') }}</title>

    <script>
        // Captura o evento de instalação o MAIS CEDO possível, antes de
        // qualquer outro script (inclusive o Alpine.js carregado logo
        // abaixo). O Chrome pode disparar "beforeinstallprompt" assim que
        // valida o manifest/service worker — se só começarmos a escutar
        // depois que o Alpine carrega e inicializa, essa corrida às vezes
        // é perdida: o Chrome já mostra a opção escondida no menu (⋮ >
        // Instalar aplicativo), mas o aviso do PRÓPRIO app nunca aparece,
        // porque o evento passou batido antes de alguém escutar.
        window.__pwaEventoInstalacao = null;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            window.__pwaEventoInstalacao = e;
        });
    </script>

    {{-- PWA: instalável na tela inicial do porteiro, em tela cheia (sem barra do navegador). --}}
    <link rel="manifest" href="{{ route('app-validador.manifest') }}">
    <meta name="theme-color" content="#0284c7">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Validador">
    <link rel="apple-touch-icon" href="{{ asset('pwa/icon-192.png') }}">
    <link rel="icon" href="{{ asset('pwa/icon-192.png') }}">

    <script>if(localStorage.getItem('theme')==='dark'||(!localStorage.getItem('theme')&&matchMedia('(prefers-color-scheme: dark)').matches))document.documentElement.classList.add('dark')</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-gray-50 text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">
    <div x-data="{
            podeInstalar: false,
            promptEvento: null,
            iosDica: false,
            init() {
                // O evento já pode ter sido capturado antes mesmo do Alpine
                // inicializar (ver script no <head>) — usa ele se já
                // existir, e continua escutando também, caso dispare só
                // agora (a ordem entre os dois pode variar).
                if (window.__pwaEventoInstalacao) {
                    this.promptEvento = window.__pwaEventoInstalacao;
                    this.podeInstalar = true;
                }
                window.addEventListener('beforeinstallprompt', (e) => {
                    e.preventDefault();
                    window.__pwaEventoInstalacao = e;
                    this.promptEvento = e;
                    this.podeInstalar = true;
                });
                const ehStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
                const ehIOS = /iphone|ipad|ipod/i.test(window.navigator.userAgent);
                if (ehIOS && !ehStandalone) { this.podeInstalar = true; this.iosDica = true; }
                if ('serviceWorker' in navigator) {
                    navigator.serviceWorker.register('{{ asset('validador-sw.js') }}', { scope: '{{ route('app-validador.index') }}' })
                        .catch((erro) => console.error('Falha ao registrar o service worker do App Validador:', erro));
                }
            },
            async instalar() {
                if (this.iosDica) return;
                if (!this.promptEvento) return;
                this.promptEvento.prompt();
                await this.promptEvento.userChoice;
                this.promptEvento = null;
                this.podeInstalar = false;
            }
         }"
    >
        <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-gray-200 bg-white/95 px-4 backdrop-blur dark:border-gray-800 dark:bg-gray-900/95 sm:px-6">
            <div class="flex min-w-0 items-center gap-3">
                <img src="{{ asset('pwa/icon-192.png') }}" alt="" class="h-9 w-9 shrink-0 rounded-lg">
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">App Validador</p>
                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $empresaAtual->nome ?? '' }}</p>
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <button type="button" class="icon-button" @click="darkMode=!darkMode; localStorage.setItem('theme',darkMode?'dark':'light')" aria-label="Alternar tema">
                    <svg x-show="!darkMode" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="1.8" d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.4 6.4L17 17M7 7 5.6 5.6m12.8 0L17 7M7 17l-1.4 1.4M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z"/></svg>
                    <svg x-cloak x-show="darkMode" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="1.8" d="M20 15.5A9 9 0 0 1 8.5 4 9 9 0 1 0 20 15.5Z"/></svg>
                </button>
                @if (\Illuminate\Support\Facades\Route::has('dashboard'))
                    <a href="{{ route('dashboard') }}" class="icon-button" aria-label="Voltar ao painel" title="Voltar ao painel">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="1.8" d="M3 10.5 12 3l9 7.5V21a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1V10.5Z"/></svg>
                    </a>
                @endif
                @if (\Illuminate\Support\Facades\Route::has('logout'))
                    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="icon-button" aria-label="Sair" title="Sair"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="1.8" d="M15 8l4 4-4 4m4-4H9m3 7H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h7"/></svg></button></form>
                @endif
            </div>
        </header>

        <main class="mx-auto w-full max-w-2xl p-4 sm:p-6">
            @include('partials.flash')

            <div x-show="podeInstalar" x-cloak class="mb-4 flex items-center justify-between gap-3 rounded-2xl border border-brand-200 bg-brand-50 p-4 text-sm dark:border-brand-500/30 dark:bg-brand-500/10">
                <div class="min-w-0">
                    <p class="font-semibold text-brand-800 dark:text-brand-300">Instale este app na tela inicial</p>
                    <p x-show="!iosDica" class="text-brand-700 dark:text-brand-400">Abre em tela cheia, sem barra do navegador — mais rápido pra usar na portaria.</p>
                    <p x-show="iosDica" class="text-brand-700 dark:text-brand-400">No iPhone: toque em <strong>Compartilhar</strong> (ícone de seta) e depois em <strong>"Adicionar à Tela de Início"</strong>.</p>
                </div>
                <button x-show="!iosDica" type="button" @click="instalar()" class="shrink-0 rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-600">Instalar</button>
            </div>

            @yield('conteudo')
        </main>
    </div>
    @stack('scripts')
</body>
</html>
