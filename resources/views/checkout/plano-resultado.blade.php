@extends('layouts.publico')

@section('titulo', 'Check-in — '.$cliente->nome)

@section('conteudo')
    @php $autorizado = $acessos->every(fn ($a) => $a->autorizado); @endphp

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
        <h1 class="text-xl font-bold text-gray-900">{{ $cliente->nome }}</h1>
        <p class="text-sm text-gray-500">{{ $situacao['plano_nome'] ?? 'Sem plano cadastrado' }}</p>

        <div class="mt-5 rounded-xl border p-4 text-center text-sm font-semibold {{ $autorizado ? 'border-emerald-300 bg-emerald-100 text-emerald-800' : 'border-rose-300 bg-rose-100 text-rose-800' }}">
            @if ($autorizado)
                ENTRADA LIBERADA — PLANO ATIVO
            @else
                ENTRADA NEGADA — {{ strtoupper($situacao['motivo_bloqueio'] ?? 'SEM PLANO ATIVO') }}
            @endif
        </div>

        @if ($autorizado && $acessos->count() > 1)
            <p class="mt-4 text-sm text-gray-600">
                Entrada liberada também para: {{ $acessos->skip(1)->map(fn ($a) => $a->nomeTitular())->implode(', ') }}
            </p>
        @endif

        @if ($autorizado)
            <p class="mt-4 text-sm text-gray-500">Baixe o(s) voucher(s) e apresente o QR na portaria para validar a entrada.</p>
            <a href="{{ $voucherUrl }}" target="_blank"
               class="mt-3 block w-full rounded-xl bg-emerald-600 py-3.5 text-center text-base font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                Baixar voucher (PDF)
            </a>
        @else
            <a href="{{ route('checkout.index', $unidade) }}" class="mt-5 block text-center text-sm text-sky-600 underline">Voltar</a>
        @endif
    </div>
@endsection
