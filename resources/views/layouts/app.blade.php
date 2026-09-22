<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Painel') · {{ $empresaAtual->nome ?? config('app.name', 'Parque Aquático') }}</title>
    <script>if(localStorage.getItem('theme')==='dark'||(!localStorage.getItem('theme')&&matchMedia('(prefers-color-scheme: dark)').matches))document.documentElement.classList.add('dark')</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body
    class="min-h-full bg-gray-50 text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100"
    x-data="appShell()"
    @keydown.escape.window="sidebarOpen = false"
>
    @php
        $menuGroups = [
            'Principal' => [
                ['dashboard', 'dashboard', 'Dashboard', 'home'],
            ],
            'Acesso' => [
                ['app-validador', 'app-validador.index', 'App Validador', 'qr-scan'],
                ['carteirinhas', 'carteirinhas.index', 'Carteirinhas', 'id'],
                ['checkin', 'checkin.index', 'Check-in (Plano)', 'check-circle'],
                ['acessos', 'acessos.index', 'Controle de Acesso', 'shield'],
                ['cortesias', 'cortesias.index', 'Cortesias', 'gift'],
                ['tipos-entrada', 'tipos-entrada.index', 'Tipos de Entrada', 'ticket'],
                ['validar-voucher', 'validacao-voucher.index', 'Validar Voucher', 'qr-scan'],
            ],
            'Cadastros' => [
                ['clientes', 'clientes.index', 'Clientes', 'users'],
                ['produtos', 'produtos.index', 'Estoque', 'box'],
                ['planos', 'planos.index', 'Planos', 'tag'],
                ['terminais', 'terminais.index', 'Terminais', 'terminal'],
                ['unidades', 'unidades.index', 'Unidades', 'building'],
                ['usuarios', 'usuarios.index', 'Usuários', 'key'],
            ],
            'Comercial' => [
                ['contratos', 'contratos.index', 'Contratos', 'document'],
                ['link-vendas', 'unidades.link-externo', 'Link de Vendas Externa', 'link'],
                ['mensalidades', 'mensalidades.index', 'Mensalidades', 'card'],
                ['vendas', 'vendas.index', 'Vendas (PDV)', 'cart'],
            ],
            'Financeiro' => [
                ['caixas', 'caixas.index', 'Caixa', 'wallet'],
                ['comissoes', 'comissoes.index', 'Comissões', 'chart'],
                ['financeiro', 'financeiro.index', 'Financeiro', 'cash'],
                ['movimentacoes', 'movimentacoes.index', 'Movimentações', 'list'],
                ['transferencias', 'transferencias.index', 'Transferências entre Caixas', 'swap'],
            ],
            'Hospedagem' => [
                ['cafe-da-manha', 'hospedagens.cafe', 'Café da Manhã', 'coffee'],
                ['hospedagens-indicadores', 'hospedagens.indicadores', 'Indicadores da Pousada', 'chart'],
                ['mapa-quartos', 'hospedagens.mapa', 'Mapa de Quartos', 'grid'],
                ['hospedagens', 'hospedagens.index', 'Pousada', 'bed'],
                ['quartos', 'quartos.index', 'Quartos', 'door'],
            ],
            'Restaurante' => [
                ['food', 'food.index', 'Mesas e Comandas', 'table'],
            ],
            'Gestão' => [
                ['auditoria', 'auditoria.index', 'Auditoria', 'search'],
                ['cidades', 'cidades.index', 'Cidades (IBGE)', 'map'],
                ['empresa', 'empresa.edit', 'Minha Empresa', 'settings'],
                ['relatorios', 'relatorios.index', 'Relatórios', 'report'],
            ],
        ];

        $menuGroupsAtivos = [];
        foreach ($menuGroups as $groupLabel => $items) {
            $grupoTemAtivo = false;
            foreach ($items as [$match, $routeName]) {
                if (! \Illuminate\Support\Facades\Route::has($routeName)) {
                    continue;
                }
                if (request()->routeIs($match) || request()->routeIs($match.'.*') || request()->routeIs($routeName)) {
                    $grupoTemAtivo = true;
                    break;
                }
            }
            $menuGroupsAtivos[\Illuminate\Support\Str::slug($groupLabel)] = $grupoTemAtivo;
        }
    @endphp

    <div x-cloak x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-gray-900/60 backdrop-blur-sm lg:hidden" @click="sidebarOpen = false"></div>

    <aside
        class="fixed inset-y-0 left-0 z-50 flex flex-col border-r border-gray-200 bg-white transition-all duration-300 dark:border-gray-800 dark:bg-gray-900"
        :class="[
            sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
            sidebarCollapsed ? 'w-[4.5rem]' : 'w-72',
            sidebarCollapsed && sidebarPinnedHover ? 'lg:!w-72' : '',
        ]"
        @mouseenter="if (sidebarCollapsed && window.matchMedia('(min-width: 1024px)').matches) sidebarPinnedHover = true"
        @mouseleave="sidebarPinnedHover = false"
        aria-label="Navegação principal"
    >
        <div class="flex h-16 shrink-0 items-center gap-2 border-b border-gray-100 px-3 dark:border-gray-800" :class="(sidebarCollapsed && !sidebarPinnedHover) ? 'justify-center' : 'justify-between px-4'">
            <a href="{{ \Illuminate\Support\Facades\Route::has('dashboard') ? route('dashboard') : '#' }}" class="flex min-w-0 items-center gap-3" :class="(sidebarCollapsed && !sidebarPinnedHover) ? 'justify-center' : ''">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-sky-600 text-white shadow-theme-sm">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="1.8" d="M3 16.5c2.5-2 4.5-2 7 0s4.5 2 7 0 3.5-2 4-1.5M4 12c2-1.5 4-1.5 6 0s4 1.5 6 0 3.5-1.5 4-1M6 7.5h12"/></svg>
                </span>
                <span class="truncate font-semibold text-gray-900 dark:text-white" x-show="!sidebarCollapsed || sidebarPinnedHover" x-cloak>{{ $empresaAtual->nome ?? 'Parque Aquático' }}</span>
            </a>
            <button
                type="button"
                class="icon-button hidden lg:inline-flex"
                x-show="!sidebarCollapsed || sidebarPinnedHover"
                x-cloak
                @click="toggleCollapsed()"
                :title="sidebarCollapsed ? 'Fixar menu expandido' : 'Recolher menu'"
                :aria-label="sidebarCollapsed ? 'Fixar menu expandido' : 'Recolher menu'"
            >
                {{-- pin / unpin --}}
                <svg x-show="!sidebarCollapsed" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 17v5m-4.5-8.5L5 16l-1-1 2.5-4.5L5 5l1-1 5.5 1.5L16 3l1 1-2.5 5.5L19 15l-1 1-4.5-2.5Z"/></svg>
                <svg x-cloak x-show="sidebarCollapsed" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m9 9 6 6m0-6-6 6M5 5l1-1 5.5 1.5L16 3l1 1-2.5 5.5L19 15l-1 1-4.5-2.5L5 16l-1-1 2.5-4.5L5 5Z"/></svg>
            </button>
            <button type="button" class="icon-button lg:hidden" @click="sidebarOpen = false" aria-label="Fechar menu"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="2" d="m6 6 12 12M18 6 6 18"/></svg></button>
        </div>

        <nav class="flex-1 overflow-y-auto px-2 py-4" :class="(sidebarCollapsed && !sidebarPinnedHover) ? 'px-2' : 'px-3'">
            <div class="space-y-1">
                @foreach ($menuGroups as $groupLabel => $items)
                    @php
                        $groupKey = \Illuminate\Support\Str::slug($groupLabel);
                        $grupoDestaque = $groupLabel === 'Acesso';
                    @endphp
                    <section class="pt-2 first:pt-0">
                        <button
                            type="button"
                            class="mb-1 flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-xs font-semibold uppercase tracking-wider transition
                                {{ $grupoDestaque
                                    ? 'bg-amber-50 text-amber-700 hover:bg-amber-100 dark:bg-amber-500/15 dark:text-amber-300 dark:hover:bg-amber-500/25'
                                    : 'text-gray-400 hover:bg-gray-50 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-white/5 dark:hover:text-gray-300' }}"
                            :class="(sidebarCollapsed && !sidebarPinnedHover) ? 'justify-center px-2' : 'justify-between'"
                            @click="toggleGroup(@js($groupKey))"
                            title="{{ $groupLabel }}{{ $grupoDestaque ? ' (mais usado)' : '' }}"
                            :aria-expanded="isGroupOpen(@js($groupKey), @js(($menuGroupsAtivos[$groupKey] ?? false) || $grupoDestaque))"
                        >
                            <span class="inline-flex items-center gap-1.5" x-show="!sidebarCollapsed || sidebarPinnedHover" x-cloak>
                                @if ($grupoDestaque)
                                    <svg class="h-3.5 w-3.5 shrink-0 text-amber-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                    </svg>
                                @endif
                                {{ $groupLabel }}
                            </span>
                            <span x-cloak x-show="sidebarCollapsed && !sidebarPinnedHover" class="relative inline-flex">
                                <span class="h-1.5 w-1.5 rounded-full {{ $grupoDestaque ? 'bg-amber-400' : 'bg-gray-300 dark:bg-gray-600' }}"></span>
                            </span>
                            <svg
                                x-show="!sidebarCollapsed || sidebarPinnedHover"
                                x-cloak
                                class="h-3.5 w-3.5 shrink-0 transition-transform {{ $grupoDestaque ? 'text-amber-600 dark:text-amber-300' : '' }}"
                                :class="isGroupOpen(@js($groupKey), @js(($menuGroupsAtivos[$groupKey] ?? false) || $grupoDestaque)) ? 'rotate-180' : ''"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            ><path stroke-linecap="round" stroke-width="2" d="m6 9 6 6 6-6"/></svg>
                        </button>

                        <div
                            class="space-y-0.5 {{ $grupoDestaque ? 'rounded-xl border border-amber-200/80 bg-amber-50/40 p-1 dark:border-amber-500/20 dark:bg-amber-500/5' : '' }}"
                            x-show="isGroupOpen(@js($groupKey), @js(($menuGroupsAtivos[$groupKey] ?? false) || $grupoDestaque)) || (sidebarCollapsed && !sidebarPinnedHover)"
                        >
                            @foreach ($items as [$match, $routeName, $label, $icon])
                                @if (\Illuminate\Support\Facades\Route::has($routeName))
                                    @php
                                        $active = request()->routeIs($match) || request()->routeIs($match.'.*') || request()->routeIs($routeName);
                                        $precisaPermissao = in_array($routeName, ['empresa.edit', 'cidades.index'], true);
                                    @endphp
                                    @if (! $precisaPermissao || auth()->user()?->can('empresa.gerenciar'))
                                        <a
                                            href="{{ route($routeName) }}"
                                            @click="sidebarOpen = false"
                                            title="{{ $label }}"
                                            class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition
                                                {{ $active
                                                    ? ($grupoDestaque
                                                        ? 'bg-amber-500 text-white shadow-sm dark:bg-amber-500 dark:text-white'
                                                        : 'bg-sky-50 text-sky-700 dark:bg-sky-500/15 dark:text-sky-400')
                                                    : ($grupoDestaque
                                                        ? 'text-amber-900/80 hover:bg-amber-100 hover:text-amber-950 dark:text-amber-100/90 dark:hover:bg-amber-500/20 dark:hover:text-amber-50'
                                                        : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white') }}"
                                            :class="(sidebarCollapsed && !sidebarPinnedHover) ? 'justify-center px-2' : ''"
                                            @if($active) aria-current="page" @endif
                                        >
                                            <x-nav-icon :name="$icon" class="h-5 w-5 shrink-0 {{ $grupoDestaque && ! $active ? 'text-amber-600 dark:text-amber-300' : '' }}" />
                                            <span class="min-w-0 flex-1 truncate" x-show="!sidebarCollapsed || sidebarPinnedHover" x-cloak>{{ $label }}</span>
                                            @if ($grupoDestaque && $routeName === 'app-validador.index')
                                                <svg x-show="!sidebarCollapsed || sidebarPinnedHover" x-cloak class="h-3.5 w-3.5 shrink-0 {{ $active ? 'text-amber-100' : 'text-amber-500' }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                                </svg>
                                            @endif
                                        </a>
                                    @endif
                                @endif
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        </nav>
    </aside>

    <div class="min-h-screen transition-all duration-300" :class="sidebarCollapsed ? 'lg:pl-[4.5rem]' : 'lg:pl-72'">
        <header class="sticky top-0 z-30 flex h-16 items-center border-b border-gray-200 bg-white/95 px-4 backdrop-blur dark:border-gray-800 dark:bg-gray-900/95 sm:px-6">
            <button
                type="button"
                class="icon-button mr-3"
                @click="window.matchMedia('(min-width: 1024px)').matches ? toggleCollapsed() : (sidebarOpen = !sidebarOpen)"
                :aria-label="sidebarCollapsed ? 'Expandir menu' : 'Recolher menu'"
                :title="sidebarCollapsed ? 'Expandir menu' : 'Recolher menu'"
            >
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <h1 class="min-w-0 flex-1 truncate text-lg font-semibold text-gray-900 dark:text-white">@yield('titulo', 'Painel')</h1>
            <div class="flex items-center gap-2 sm:gap-3">
                <button type="button" class="icon-button" @click="toggleDark()" aria-label="Alternar tema">
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

    <script>
        function appShell() {
            const savedGroups = (() => {
                try { return JSON.parse(localStorage.getItem('menuGroupsOpen') || '{}'); } catch (e) { return {}; }
            })();

            return {
                darkMode: document.documentElement.classList.contains('dark'),
                sidebarOpen: false,
                sidebarCollapsed: localStorage.getItem('sidebarCollapsed') === '1',
                sidebarPinnedHover: false,
                openGroups: savedGroups,
                toggleDark() {
                    this.darkMode = !this.darkMode;
                    document.documentElement.classList.toggle('dark', this.darkMode);
                    localStorage.setItem('theme', this.darkMode ? 'dark' : 'light');
                },
                toggleCollapsed() {
                    this.sidebarCollapsed = !this.sidebarCollapsed;
                    this.sidebarPinnedHover = false;
                    localStorage.setItem('sidebarCollapsed', this.sidebarCollapsed ? '1' : '0');
                },
                toggleGroup(key) {
                    if (this.sidebarCollapsed && !this.sidebarPinnedHover) {
                        this.sidebarCollapsed = false;
                        localStorage.setItem('sidebarCollapsed', '0');
                    }
                    const next = !this.isGroupOpen(key, false);
                    this.openGroups = { ...this.openGroups, [key]: next };
                    localStorage.setItem('menuGroupsOpen', JSON.stringify(this.openGroups));
                },
                isGroupOpen(key, hasActive) {
                    if (Object.prototype.hasOwnProperty.call(this.openGroups, key)) {
                        return !!this.openGroups[key];
                    }
                    // Acesso fica aberto por padrão (módulo mais usado).
                    if (key === 'acesso') {
                        return true;
                    }
                    return !!hasActive;
                },
            };
        }
    </script>
    @stack('scripts')
</body>
</html>
