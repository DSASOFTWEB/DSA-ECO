<!DOCTYPE html>
<html lang="pt-BR" class="h-full overflow-hidden">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'PDV') · {{ $empresaAtual->nome ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="h-full overflow-hidden bg-slate-100 text-slate-800 antialiased">
    @yield('conteudo')
    @stack('scripts')
</body>
</html>
