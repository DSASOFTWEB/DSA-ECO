@extends('layouts.app')

@section('titulo', 'Contrato '.$contrato->numero_contrato)

@section('conteudo')
    @if (session('contrato_recem_criado') === $contrato->id)
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border-2 border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
            <span>Contrato criado! O documento de adesão já pode ser impresso ou enviado ao cliente.</span>
            <a href="{{ route('contratos.pdf', $contrato) }}" target="_blank" class="shrink-0 rounded-lg bg-emerald-600 px-4 py-2 font-semibold text-white hover:bg-emerald-700">Abrir contrato em PDF</a>
        </div>
    @endif

    <div class="mb-6 flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-gray-800 dark:text-white/90">{{ $contrato->cliente->nome }}</h2>
            <p class="text-sm text-slate-500">{{ $contrato->plano->nome }} · <x-status-badge :status="$contrato->status" /></p>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('contratos.pdf', $contrato) }}" target="_blank" class="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">Contrato em PDF</a>

            @if ($contrato->estaAtivo())
                @can('update', $contrato)
                    <a href="{{ route('contratos.edit', $contrato) }}" class="inline-flex items-center rounded-lg border border-sky-300 bg-sky-50 px-4 py-2 text-sm font-medium text-sky-800 hover:bg-sky-100 dark:border-sky-700 dark:bg-sky-500/10 dark:text-sky-300">Editar plano / vencimento</a>
                    <a href="{{ route('contratos.prorrogar-form', $contrato) }}" class="inline-flex items-center rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-800 hover:bg-amber-100 dark:border-amber-700 dark:bg-amber-500/10 dark:text-amber-300">Prorrogar cobrança</a>
                @endcan
                @can('cancelar', $contrato)
                    <form method="POST" action="{{ route('contratos.cancelar', $contrato) }}" onsubmit="return confirm('Cancelar este contrato? As mensalidades futuras pendentes serão canceladas.')" class="flex gap-2">
                        @csrf
                        <input type="text" name="motivo_cancelamento" required placeholder="Motivo do cancelamento" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <button class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Cancelar contrato</button>
                    </form>
                @endcan
            @elseif ($contrato->estaCancelado())
                @can('reativar', $contrato)
                    <form method="POST" action="{{ route('contratos.reativar', $contrato) }}" onsubmit="return confirm('Reativar este contrato? Uma nova mensalidade da competência atual será gerada.')">
                        @csrf
                        <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Reativar contrato</button>
                    </form>
                @endcan
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
            <h3 class="mb-3 text-sm font-semibold text-slate-700">Dados do contrato</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-400">Número</dt><dd>{{ $contrato->numero_contrato }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-400">Valor mensal</dt><dd>R$ {{ number_format($contrato->valor_mensal, 2, ',', '.') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-400">Caução / entrada</dt><dd>R$ {{ number_format($contrato->valor_caucao, 2, ',', '.') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-400">Desconto</dt><dd>{{ $contrato->desconto_percentual }}%</dd></div>
                <div class="flex justify-between"><dt class="text-slate-400">Vencimento</dt><dd>dia {{ $contrato->dia_vencimento }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-400">Início</dt><dd>{{ $contrato->data_inicio->format('d/m/Y') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-400">Primeira mensalidade</dt><dd>{{ $contrato->primeiro_vencimento?->format('d/m/Y') ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-400">Vendedor</dt><dd>{{ $contrato->vendedor?->name ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-400">Unidade</dt><dd>{{ $contrato->unidade->nome }}</dd></div>
                @if ($contrato->cancelado_em)
                    <div class="flex justify-between"><dt class="text-slate-400">Cancelado em</dt><dd>{{ $contrato->cancelado_em->format('d/m/Y H:i') }}</dd></div>
                @endif
                @if ($contrato->reativado_em)
                    <div class="flex justify-between"><dt class="text-slate-400">Reativado em</dt><dd>{{ $contrato->reativado_em->format('d/m/Y H:i') }}</dd></div>
                @endif
            </dl>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-6 lg:col-span-2">
            <h3 class="mb-3 text-sm font-semibold text-slate-700">Mensalidades</h3>
            <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase text-slate-400">
                    <tr><th class="py-2">Tipo</th><th>Competência</th><th>Vencimento</th><th>Valor</th><th>Status</th><th></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse ($contrato->mensalidades as $mensalidade)
                        <tr>
                            <td class="py-2">{{ $mensalidade->ehCaucao() ? 'Caução / entrada' : 'Mensalidade' }}</td>
                            <td class="py-2">{{ $mensalidade->competencia->format('m/Y') }}</td>
                            <td class="py-2 text-slate-500">{{ $mensalidade->data_vencimento->format('d/m/Y') }}</td>
                            <td class="py-2 text-slate-500">R$ {{ number_format($mensalidade->valor_total, 2, ',', '.') }}</td>
                            <td class="py-2"><x-status-badge :status="$mensalidade->status" /></td>
                            <td class="py-2 text-right"><a href="{{ route('mensalidades.show', $mensalidade) }}" class="text-sky-700 hover:underline">Ver</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-slate-400">Nenhuma cobrança gerada ainda.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
