<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Relatório') · {{ $empresaAtual->nome ?? config('app.name') }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        body { background: #fff; color: #0f172a; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body class="antialiased">
    <div class="no-print flex items-center justify-center gap-2 bg-slate-900 p-3">
        <button type="button" onclick="window.print()" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">Imprimir / salvar PDF</button>
        <button type="button" onclick="window.close()" class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-800">Fechar</button>
    </div>
    <main class="mx-auto max-w-[1100px] p-6">
        @yield('conteudo')
    </main>
    <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
</body>
</html>
