<!DOCTYPE html>
<html lang="pt-BR" class="h-full" x-data="{ darkMode: localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && matchMedia('(prefers-color-scheme: dark)').matches) }" :class="{ 'dark': darkMode }">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Redefinir senha · {{ config('app.name') }}</title>
    <script>if(localStorage.getItem('theme')==='dark'||(!localStorage.getItem('theme')&&matchMedia('(prefers-color-scheme: dark)').matches))document.documentElement.classList.add('dark')</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-white text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">
    <main class="relative grid min-h-screen lg:grid-cols-2">
        <button type="button" class="icon-button absolute right-5 top-5 z-20 border border-gray-200 bg-white shadow-theme-sm dark:border-gray-700 dark:bg-gray-900" @click="darkMode=!darkMode; localStorage.setItem('theme',darkMode?'dark':'light')" aria-label="Alternar tema">
            <svg x-show="!darkMode" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="1.8" d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.4 6.4L17 17M7 7 5.6 5.6m12.8 0L17 7M7 17l-1.4 1.4M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z"/></svg>
            <svg x-cloak x-show="darkMode" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="1.8" d="M20 15.5A9 9 0 0 1 8.5 4 9 9 0 1 0 20 15.5Z"/></svg>
        </button>

        <section class="flex items-center justify-center px-5 py-16 sm:px-10 lg:px-16">
            <div class="w-full max-w-md">
                <div class="mb-8 lg:hidden">
                    <span class="inline-grid h-12 w-12 place-items-center rounded-2xl bg-sky-600 text-white shadow-theme-sm">
                        <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="1.8" d="M3 16.5c2.5-2 4.5-2 7 0s4.5 2 7 0 3.5-2 4-1.5M4 12c2-1.5 4-1.5 6 0s4 1.5 6 0 3.5-1.5 4-1M6 7.5h12"/></svg>
                    </span>
                </div>

                <div class="mb-8">
                    <p class="mb-2 text-sm font-medium text-sky-600 dark:text-sky-400">Quase lá</p>
                    <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Crie uma nova senha</h1>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Escolha uma senha nova para sua conta.</p>
                </div>

                @include('partials.flash')

                <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">

                    <div>
                        <label for="email" class="form-label">E-mail</label>
                        <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required autofocus autocomplete="email"
                               class="form-control @error('email') border-rose-500 focus:border-rose-500 focus:ring-rose-500 @enderror"
                               placeholder="seu@email.com">
                        @error('email')<p class="mt-1.5 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="password" class="form-label">Nova senha</label>
                        <input id="password" type="password" name="password" required autocomplete="new-password"
                               class="form-control @error('password') border-rose-500 focus:border-rose-500 focus:ring-rose-500 @enderror"
                               placeholder="Digite a nova senha">
                        @error('password')<p class="mt-1.5 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="form-label">Confirmar nova senha</label>
                        <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                               class="form-control" placeholder="Digite a nova senha de novo">
                    </div>

                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-sky-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700 focus-visible:ring-sky-500 disabled:opacity-50">Redefinir senha</button>
                </form>
            </div>
        </section>

        <aside class="relative hidden overflow-hidden bg-sky-950 lg:flex lg:items-center lg:justify-center" aria-hidden="true">
            <div class="absolute inset-0 opacity-30" style="background-image: radial-gradient(circle at 20% 20%, #38bdf8 0, transparent 30%), radial-gradient(circle at 80% 75%, #0ea5e9 0, transparent 35%)"></div>
            <div class="absolute -left-24 top-1/3 h-72 w-72 rounded-full border border-white/10"></div>
            <div class="absolute -right-20 bottom-10 h-96 w-96 rounded-full border border-white/10"></div>
            <div class="relative max-w-lg px-12 text-center text-white">
                <span class="mx-auto grid h-20 w-20 place-items-center rounded-3xl bg-white/10 ring-1 ring-white/20 backdrop-blur">
                    <svg class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-width="1.5" d="M3 16.5c2.5-2 4.5-2 7 0s4.5 2 7 0 3.5-2 4-1.5M4 12c2-1.5 4-1.5 6 0s4 1.5 6 0 3.5-1.5 4-1M6 7.5h12"/></svg>
                </span>
                <h2 class="mt-7 text-3xl font-bold tracking-tight">{{ config('app.name') }}</h2>
                <p class="mt-3 text-base leading-7 text-sky-100">Gestão simples e centralizada para sua operação.</p>
            </div>
        </aside>
    </main>
</body>
</html>
