@extends('layouts.app')

@section('titulo', 'Check-in — '.$cliente->nome)

@section('conteudo')
    <div class="max-w-2xl space-y-5">
        <a href="{{ route('checkin.index') }}" class="text-sm text-brand-600 hover:underline">&larr; Nova busca</a>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-xl font-bold text-gray-800 dark:text-white/90">{{ $cliente->nome }}</h2>
                    <p class="text-sm text-slate-500">CPF: {{ $cliente->cpf ?? '—' }} · Cliente #{{ $cliente->id }}</p>
                </div>
                <x-status-badge :status="$cliente->status" />
            </div>

            @if ($situacao['tem_plano'])
                <dl class="mt-5 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                    <div><dt class="text-slate-400">Plano</dt><dd class="font-medium text-gray-800 dark:text-white/90">{{ $situacao['plano_nome'] }}</dd></div>
                    <div><dt class="text-slate-400">Status do contrato</dt><dd><x-status-badge :status="$situacao['status']" /></dd></div>
                    <div><dt class="text-slate-400">Início</dt><dd class="font-medium">{{ $situacao['data_inicio']?->format('d/m/Y') ?? '—' }}</dd></div>
                    <div><dt class="text-slate-400">Vencimento do contrato</dt><dd class="font-medium">{{ $situacao['data_fim']?->format('d/m/Y') ?? 'Sem data fim' }}</dd></div>
                    <div><dt class="text-slate-400">Último pagamento</dt><dd class="font-medium">{{ $situacao['ultimo_pagamento']?->format('d/m/Y') ?? 'Nenhum pagamento registrado' }}</dd></div>
                </dl>

            @endif

            <div class="mt-6 rounded-xl border p-4 text-sm font-semibold {{ $situacao['ativo'] ? 'border-emerald-300 bg-emerald-100 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400' : 'border-rose-300 bg-rose-100 text-rose-800 dark:border-rose-700 dark:bg-rose-500/15 dark:text-rose-400' }}">
                @if ($situacao['ativo'])
                    ENTRADA LIBERADA — CLIENTE COM PLANO ATIVO
                @else
                    ENTRADA NEGADA — {{ strtoupper($situacao['motivo_bloqueio'] ?? 'SEM PLANO ATIVO') }}
                @endif
            </div>

            <form method="POST" action="{{ route('checkin.registrar', $cliente) }}" class="mt-5">
                @csrf

                @if ($situacao['ativo'] && $situacao['tem_plano'] && $situacao['contrato']->dependentes->isNotEmpty())
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-white/[0.03]">
                        <p class="mb-2 text-sm font-medium text-slate-700">Quem está entrando agora?</p>
                        <p class="mb-3 text-xs text-slate-400">Desmarque quem não veio hoje — só quem ficar marcado recebe entrada e sai no comprovante impresso.</p>
                        <div class="space-y-2">
                            <label class="flex items-center gap-2 text-sm font-medium text-gray-800 dark:text-white/90">
                                <input type="checkbox" checked disabled class="rounded border-slate-300">
                                {{ $cliente->nome }} <span class="text-xs font-normal text-slate-400">(titular, sempre entra)</span>
                            </label>
                            @foreach ($situacao['contrato']->dependentes as $dependente)
                                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    <input type="checkbox" name="dependentes[]" value="{{ $dependente->id }}" checked class="rounded border-slate-300">
                                    {{ $dependente->nome }} <span class="text-xs text-slate-400">(dependente)</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="mt-5 flex justify-end">
                    <button class="h-11 rounded-lg px-6 text-sm font-semibold text-white shadow-theme-xs transition {{ $situacao['ativo'] ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-rose-600 hover:bg-rose-700' }}">
                        {{ $situacao['ativo'] ? 'Confirmar entrada' : 'Registrar tentativa negada' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
