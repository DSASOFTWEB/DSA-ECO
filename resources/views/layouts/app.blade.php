<!DOCTYPE html>
<html lang="pt-BR" class="h-full" x-data="{ darkMode: localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && matchMedia('(prefers-color-scheme: dark)').matches) }" :class="{ 'dark': darkMode }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Painel') · {{ $empresaAtual->nome ?? config('app.name', 'Parque Aquático') }}</title>
    <script>if(localStorage.getItem('theme')==='dark'||(!localStorage.getItem('theme')&&matchMedia('(prefers-color-scheme: dark)').matches))document.documentElement.classList.add('dark')</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="min-h-full bg-gray-50 text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100" x-data="{ sidebarOpen: false }">
    @php
        $menu = [
            ['dashboard', 'dashboard', 'Dashboard', 'home'],
            ['clientes', 'clientes.index', 'Clientes', 'users'],
            ['contratos', 'contratos.index', 'Contratos', 'document'],
            ['planos', 'planos.index', 'Planos', 'tag'],
            ['mensalidades', 'mensalidades.index', 'Mensalidades', 'card'],
            ['financeiro', 'financeiro.index', 'Financeiro', 'cash'],
            ['terminais', 'terminais.index', 'Terminais', 'terminal'],
            ['caixas', 'caixas.index', 'Caixa', 'wallet'],
            ['vendas', 'vendas.index', 'Vendas (PDV)', 'cart'],
            ['food', 'food.index', 'Mesas e Comandas', 'table'],
            ['hospedagens', 'hospedagens.index', 'Pousada', 'bed'],
            ['quartos', 'quartos.index', 'Quartos', 'door'],
            ['tipos-entrada', 'tipos-entrada.index', 'Tipos de Entrada', 'ticket'],
            ['produtos', 'produtos.index', 'Estoque', 'box'],
            ['comissoes', 'comissoes.index', 'Comissões', 'chart'],
            ['carteirinhas', 'carteirinhas.index', 'Carteirinhas', 'id'],
            ['checkin', 'checkin.index', 'Check-in (plano)', 'check-circle'],
            ['validar-voucher', 'validacao-voucher.index', 'Validar Voucher', 'qr-scan'],
            ['cortesias', 'cortesias.index', 'Cortesias', 'gift'],
            ['acessos', 'acessos.index', 'Controle de Acesso', 'shield'],
            ['relatorios', 'relatorios.index', 'Relatórios', 'report'],
            ['unidades', 'unidades.index', 'Unidades', 'building'],
            ['empresa', 'empresa.edit', 'Minha Empresa', 'settings'],
            ['usuarios', 'usuarios.index', 'Usuários', 'key'],
            ['auditoria', 'auditoria.index', 'Auditoria', 'search'],
            ['app-validador', 'app-validador.index', 'App Validador', 'qr-scan'],
            ['link-vendas', 'unidades.link-externo', 'Link de Vendas Externa', 'link'],
        ];
    @endphp

    <div x-cloak x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-gray-900/60 backdrop-blur-sm lg:hidden" @click="sidebarOpen = false"></div>
    <aside class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col border-r border-gray-200 bg-white transition-transform duration-300 dark:border-gray-800 dark:bg-gray-900 lg:translate-x-0"
           :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'" aria-label="Navegação principal">
        <div class="flex h-20 items-center justify-between border-b border-gray-100 px-6 dark:border-gray-800">
            <a href="{{ \Illuminate\Support\Facades\Route::has('dashboard') ? route('dashboard') : '#' }}" class="flex min-w-0 items-center gap-3">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-sky-600 text-white shadow-theme-sm">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="1.8" d="M3 16.5c2.5-2 4.5-2 7 0s4.5 2 7 0 3.5-2 4-1.5M4 12c2-1.5 4-1.5 6 0s4 1.5 6 0 3.5-1.5 4-1M6 7.5h12"/></svg>
                </span>
                <span class="truncate font-semibold text-gray-900 dark:text-white">{{ $empresaAtual->nome ?? 'Parque Aquático' }}</span>
            </a>
            <button type="button" class="icon-button lg:hidden" @click="sidebarOpen = false" aria-label="Fechar menu"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="2" d="m6 6 12 12M18 6 6 18"/></svg></button>
        </div>
        <nav class="flex-1 overflow-y-auto px-4 py-6">
            <p class="mb-3 px-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Menu</p>
            <div class="space-y-1">
                @foreach ($menu as [$match, $routeName, $label, $icon])
                    @if (\Illuminate\Support\Facades\Route::has($routeName))
                        @php
                            $active = request()->routeIs($match) || request()->routeIs($match.'.*') || request()->routeIs($routeName);
                            $precisaPermissao = $routeName === 'empresa.edit';
                        @endphp
                        @if (! $precisaPermissao || auth()->user()?->can('empresa.gerenciar'))
                            <a href="{{ route($routeName) }}" @click="sidebarOpen = false" class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ $active ? 'bg-sky-50 text-sky-700 dark:bg-sky-500/15 dark:text-sky-400' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white' }}" @if($active) aria-current="page" @endif>
                                <x-nav-icon :name="$icon" class="h-5 w-5 shrink-0" />
                                <span>{{ $label }}</span>
                            </a>
                        @endif
                    @endif
                @endforeach
            </div>
        </nav>
    </aside>

    <div class="min-h-screen lg:pl-72">
        <header class="sticky top-0 z-30 flex h-16 items-center border-b border-gray-200 bg-white/95 px-4 backdrop-blur dark:border-gray-800 dark:bg-gray-900/95 sm:px-6">
            <button type="button" class="icon-button mr-3 lg:hidden" @click="sidebarOpen = true" aria-label="Abrir menu"><svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg></button>
            <h1 class="min-w-0 flex-1 truncate text-lg font-semibold text-gray-900 dark:text-white">@yield('titulo', 'Painel')</h1>
            <div class="flex items-center gap-2 sm:gap-3">
                <button type="button" class="icon-button" @click="darkMode=!darkMode; localStorage.setItem('theme',darkMode?'dark':'light')" aria-label="Alternar tema">
                    <svg x-show="!darkMode" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="1.8" d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.4 6.4L17 17M7 7 5.6 5.6m12.8 0L17 7M7 17l-1.4 1.4M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z"/></svg>
                    <svg x-cloak x-show="darkMode" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="1.8" d="M20 15.5A9 9 0 0 1 8.5 4 9 9 0 1 0 20 15.5Z"/></svg>
                </button>
                @auth
                    <div class="hidden text-right md:block"><p class="max-w-40 truncate text-sm font-medium">{{ auth()->user()->name }}</p><p class="max-w-40 truncate text-xs text-gray-500">{{ $unidadeAtual->nome ?? 'Todas as unidades' }}</p></div>
                    @can('empresa.gerenciar')
                        @if (\Illuminate\Support\Facades\Route::has('empresa.edit'))
                            <a href="{{ route('empresa.edit') }}" class="icon-button" aria-label="Minha Empresa" title="Minha Empresa / Impressão">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="1.8" d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm8.4-3a7.9 7.9 0 0 0-.15-1.5l2-1.6-2-3.4-2.4 1a7.6 7.6 0 0 0-2.6-1.5L15 2h-4l-.4 2.9a7.6 7.6 0 0 0-2.6 1.5l-2.4-1-2 3.4 2 1.6a8 8 0 0 0 0 3l-2 1.6 2 3.4 2.4-1a7.6 7.6 0 0 0 2.6 1.5L11 22h4l.4-2.9a7.6 7.6 0 0 0 2.6-1.5l2.4 1 2-3.4-2-1.6c.1-.5.15-1 .15-1.5Z"/></svg>
                            </a>
                        @endif
                    @endcan
                    @if (\Illuminate\Support\Facades\Route::has('logout'))
                        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="icon-button" aria-label="Sair" title="Sair"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="1.8" d="M15 8l4 4-4 4m4-4H9m3 7H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h7"/></svg></button></form>
                    @endif
                @endauth
            </div>
        </header>
        <main class="mx-auto w-full max-w-screen-2xl p-4 sm:p-6 lg:p-8">
            @include('partials.flash')
            {{ $slot ?? '' }}
            @yield('conteudo')
        </main>
    </div>
    @stack('scripts')
</body>
</html>
