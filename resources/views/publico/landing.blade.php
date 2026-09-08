<!DOCTYPE html>
<html lang="pt-BR" class="h-full scroll-smooth" x-data="{ darkMode: localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && matchMedia('(prefers-color-scheme: dark)').matches) }" :class="{ 'dark': darkMode }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} · Gestão para parques aquáticos</title>
    <script>if(localStorage.getItem('theme')==='dark'||(!localStorage.getItem('theme')&&matchMedia('(prefers-color-scheme: dark)').matches))document.documentElement.classList.add('dark')</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-white text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">

    {{-- Cabeçalho --}}
    <header class="sticky top-0 z-40 border-b border-gray-100 bg-white/90 backdrop-blur dark:border-gray-800 dark:bg-gray-950/90">
        <div class="mx-auto flex h-18 max-w-7xl items-center justify-between gap-4 px-5 py-3.5 sm:px-8">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-sky-600 text-white shadow-theme-sm">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="1.8" d="M3 16.5c2.5-2 4.5-2 7 0s4.5 2 7 0 3.5-2 4-1.5M4 12c2-1.5 4-1.5 6 0s4 1.5 6 0 3.5-1.5 4-1M6 7.5h12"/></svg>
                </span>
                <span class="text-lg font-bold tracking-tight text-gray-900 dark:text-white">{{ config('app.name') }}</span>
            </a>

            <nav class="hidden items-center gap-8 text-sm font-medium text-gray-600 dark:text-gray-400 md:flex">
                <a href="#recursos" class="hover:text-sky-600 dark:hover:text-sky-400">Recursos</a>
                <a href="#modulos" class="hover:text-sky-600 dark:hover:text-sky-400">Módulos</a>
                <a href="#planos" class="hover:text-sky-600 dark:hover:text-sky-400">Como funciona</a>
            </nav>

            <div class="flex items-center gap-2 sm:gap-3">
                <button type="button" class="icon-button" @click="darkMode=!darkMode; localStorage.setItem('theme',darkMode?'dark':'light')" aria-label="Alternar tema">
                    <svg x-show="!darkMode" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="1.8" d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.4 6.4L17 17M7 7 5.6 5.6m12.8 0L17 7M7 17l-1.4 1.4M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z"/></svg>
                    <svg x-cloak x-show="darkMode" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="1.8" d="M20 15.5A9 9 0 0 1 8.5 4 9 9 0 1 0 20 15.5Z"/></svg>
                </button>
                <a href="{{ route('login') }}" class="hidden rounded-lg px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5 sm:inline-flex">Acessar</a>
                <a href="{{ route('cadastro.create') }}" class="inline-flex items-center rounded-lg bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">Criar minha conta</a>
            </div>
        </div>
    </header>

    {{-- Hero --}}
    <section class="mx-auto max-w-7xl px-5 pb-20 pt-14 sm:px-8 sm:pt-20">
        <div class="grid items-center gap-14 lg:grid-cols-2">
            <div>
                <p class="mb-3 text-sm font-semibold uppercase tracking-wide text-sky-600 dark:text-sky-400">Gestão de parques aquáticos</p>
                <h1 class="text-4xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-5xl">
                    Sua operação com <span class="text-sky-600 dark:text-sky-400">agilidade</span>.
                </h1>
                <p class="mt-5 text-lg leading-8 text-gray-500 dark:text-gray-400">
                    O {{ config('app.name') }} acompanha a rotina real do seu parque: clientes e planos, contratos e mensalidades,
                    PDV com caixa, controle de acesso por QR/catraca e o financeiro completo — tudo em um só painel.
                </p>

                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('cadastro.create') }}" class="inline-flex items-center justify-center rounded-lg bg-sky-600 px-6 py-3.5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">Criar minha conta grátis</a>
                    <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-6 py-3.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-white/5">Já tenho conta</a>
                </div>
                <p class="mt-3 text-xs text-gray-400">14 dias grátis · sem cartão de crédito · cancele quando quiser</p>

                <dl class="mt-10 grid grid-cols-1 gap-5 sm:grid-cols-2">
                    @foreach ([
                        ['icon' => 'M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z', 'cor' => 'bg-sky-100 text-sky-600 dark:bg-sky-500/15 dark:text-sky-400', 'titulo' => 'Contratos automáticos', 'texto' => 'Mensalidades, atrasos e cobrança geradas sozinhas.'],
                        ['icon' => 'M4 8V6a2 2 0 0 1 2-2h2M4 16v2a2 2 0 0 0 2 2h2m8-16h2a2 2 0 0 1 2 2v2m-4 12h2a2 2 0 0 0 2-2v-2M8 12h8', 'cor' => 'bg-violet-100 text-violet-600 dark:bg-violet-500/15 dark:text-violet-400', 'titulo' => 'Acesso por QR/catraca', 'texto' => 'Validação na portaria, sem fraude em voucher online.'],
                        ['icon' => 'M3 3h2l2.4 11.3a2 2 0 0 0 2 1.7h8.8a2 2 0 0 0 2-1.6L22 7H6', 'cor' => 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400', 'titulo' => 'PDV integrado', 'texto' => 'Venda avulsa, produtos e caixa conciliado na hora.'],
                        ['icon' => 'M2 6h20v12H2V6Zm10 3a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z', 'cor' => 'bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400', 'titulo' => 'Financeiro completo', 'texto' => 'Contas a pagar/receber, caixa e relatórios num só lugar.'],
                    ] as $item)
                        <div class="flex items-start gap-3">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl {{ $item['cor'] }}">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $item['icon'] }}"/></svg>
                            </span>
                            <div>
                                <dt class="text-sm font-semibold text-gray-800 dark:text-white/90">{{ $item['titulo'] }}</dt>
                                <dd class="text-sm text-gray-500 dark:text-gray-400">{{ $item['texto'] }}</dd>
                            </div>
                        </div>
                    @endforeach
                </dl>
            </div>

            {{-- Preview estilizado do painel (mesma linguagem visual do dashboard real) --}}
            <div class="relative">
                <div class="absolute -inset-6 -z-10 rounded-[2rem] bg-gradient-to-br from-sky-100 via-transparent to-violet-100 blur-2xl dark:from-sky-500/10 dark:to-violet-500/10"></div>
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-center gap-1.5 border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                        <span class="h-2.5 w-2.5 rounded-full bg-rose-400"></span>
                        <span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>
                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
                        <span class="ml-3 text-xs font-medium text-gray-400">Painel · Dashboard</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3 p-5">
                        <div class="rounded-xl border border-sky-200 bg-sky-50 p-4 dark:border-sky-800 dark:bg-sky-500/10">
                            <p class="text-[11px] font-medium uppercase tracking-wide text-sky-500">Clientes ativos</p>
                            <p class="mt-1 text-2xl font-bold text-sky-700 dark:text-sky-400">248</p>
                        </div>
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-800 dark:bg-emerald-500/10">
                            <p class="text-[11px] font-medium uppercase tracking-wide text-emerald-500">Faturamento do mês</p>
                            <p class="mt-1 text-2xl font-bold text-emerald-700 dark:text-emerald-400">R$ 42.180</p>
                        </div>
                        <div class="rounded-xl border border-violet-200 bg-violet-50 p-4 dark:border-violet-800 dark:bg-violet-500/10">
                            <p class="text-[11px] font-medium uppercase tracking-wide text-violet-500">Entradas hoje</p>
                            <p class="mt-1 text-2xl font-bold text-violet-700 dark:text-violet-400">312</p>
                        </div>
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-500/10">
                            <p class="text-[11px] font-medium uppercase tracking-wide text-amber-500">Ticket médio</p>
                            <p class="mt-1 text-2xl font-bold text-amber-700 dark:text-amber-400">R$ 68,50</p>
                        </div>
                        <div class="col-span-2 rounded-xl border border-gray-100 bg-gray-50 p-4 dark:border-gray-800 dark:bg-white/[0.03]">
                            <p class="mb-2 text-[11px] font-medium uppercase tracking-wide text-gray-400">Entradas por hora</p>
                            <div class="flex h-14 items-end gap-1.5">
                                @foreach ([30,45,60,80,55,90,70,40] as $h)
                                    <div class="flex-1 rounded-t bg-cyan-400/70 dark:bg-cyan-500/60" style="height: {{ $h }}%"></div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="absolute -bottom-6 left-6 flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-xs font-semibold text-gray-700 shadow-theme-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 sm:left-10">
                    <span class="grid h-6 w-6 place-items-center rounded-full bg-sky-600 text-white">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7Z"/></svg>
                    </span>
                    Menos tempo na planilha. Mais tempo no parque.
                </div>
            </div>
        </div>
    </section>

    {{-- Módulos --}}
    <section id="modulos" class="border-t border-gray-100 bg-gray-50 py-20 dark:border-gray-800 dark:bg-white/[0.02]">
        <div class="mx-auto max-w-7xl px-5 sm:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <p class="text-sm font-semibold uppercase tracking-wide text-sky-600 dark:text-sky-400">Tudo integrado</p>
                <h2 class="mt-2 text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Um sistema, todos os módulos do parque</h2>
            </div>

            <div id="recursos" class="mt-14 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['icon' => 'users', 'cor' => 'bg-sky-100 text-sky-600 dark:bg-sky-500/15 dark:text-sky-400', 'titulo' => 'Clientes & Contratos', 'texto' => 'Cadastro, dependentes, planos e carteirinha digital com QR.'],
                    ['icon' => 'cash', 'cor' => 'bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400', 'titulo' => 'Financeiro', 'texto' => 'Mensalidades, contas a pagar/receber e caixa em um só painel.'],
                    ['icon' => 'cart', 'cor' => 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400', 'titulo' => 'PDV & Caixa', 'texto' => 'Venda de produtos e entradas avulsas com abertura/fechamento de caixa.'],
                    ['icon' => 'qr-scan', 'cor' => 'bg-violet-100 text-violet-600 dark:bg-violet-500/15 dark:text-violet-400', 'titulo' => 'Controle de acesso', 'texto' => 'Check-in por QR, cortesias e validação antifraude na portaria.'],
                ] as $modulo)
                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-gray-800 dark:bg-gray-900">
                        <span class="grid h-12 w-12 place-items-center rounded-xl {{ $modulo['cor'] }}">
                            <x-nav-icon :name="$modulo['icon']" class="h-6 w-6" />
                        </span>
                        <h3 class="mt-4 text-base font-semibold text-gray-900 dark:text-white">{{ $modulo['titulo'] }}</h3>
                        <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">{{ $modulo['texto'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- CTA final --}}
    <section id="planos" class="mx-auto max-w-5xl px-5 py-20 text-center sm:px-8">
        <h2 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Pronto para simplificar a gestão do seu parque?</h2>
        <p class="mx-auto mt-3 max-w-xl text-base text-gray-500 dark:text-gray-400">Crie a conta da sua empresa agora e comece a usar hoje mesmo — 14 dias grátis, sem compromisso.</p>
        <div class="mt-8">
            <a href="{{ route('cadastro.create') }}" class="inline-flex items-center justify-center rounded-lg bg-sky-600 px-8 py-3.5 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">Criar minha conta grátis</a>
        </div>
    </section>

    <footer class="border-t border-gray-100 py-8 text-center text-xs text-gray-400 dark:border-gray-800">
        &copy; {{ now()->year }} {{ config('app.name') }}. Todos os direitos reservados.
    </footer>
</body>
</html>
