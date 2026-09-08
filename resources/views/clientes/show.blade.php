@extends('layouts.app')

@section('titulo', $cliente->nome)

@section('conteudo')
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="grid h-16 w-16 shrink-0 place-items-center overflow-hidden rounded-full border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800">
                @if ($cliente->fotoUrl())
                    <img src="{{ $cliente->fotoUrl() }}" alt="{{ $cliente->nome }}" class="h-full w-full object-cover">
                @else
                    <span class="text-lg font-bold text-gray-400">{{ collect(explode(' ', $cliente->nome))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}</span>
                @endif
            </div>
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-gray-800 dark:text-white/90">{{ $cliente->nome }}</h2>
                <p class="text-sm text-slate-500">CPF {{ $cliente->cpf }} · <x-status-badge :status="$cliente->status" /></p>
            </div>
        </div>
        <div class="flex gap-2">
            @can('update', $cliente)
                <a href="{{ route('clientes.edit', $cliente) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Editar</a>
            @endcan
            @if (! $cliente->contratoAtivo && \Illuminate\Support\Facades\Route::has('contratos.create'))
                @can('create', \App\Models\Contrato::class)
                    <a href="{{ route('contratos.create', ['cliente_id' => $cliente->id]) }}" class="rounded-lg bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-800">
                        + Novo contrato
                    </a>
                @endcan
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
                <h3 class="mb-3 text-sm font-semibold text-slate-700">Contato</h3>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-slate-400">E-mail</dt><dd>{{ $cliente->email ?: '—' }}</dd></div>
                    <div><dt class="text-slate-400">Telefone</dt><dd>{{ $cliente->telefone ?: '—' }}</dd></div>
                    <div><dt class="text-slate-400">WhatsApp</dt><dd>{{ $cliente->whatsapp ?: '—' }}</dd></div>
                    <div><dt class="text-slate-400">Unidade</dt><dd>{{ $cliente->unidade?->nome ?: '—' }}</dd></div>
                </dl>
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
                <h3 class="mb-3 text-sm font-semibold text-slate-700">Contratos</h3>
                <table class="min-w-full text-sm">
                    <tbody class="divide-y divide-slate-50">
                        @forelse ($cliente->contratos as $contrato)
                            <tr>
                                <td class="py-2">{{ $contrato->plano->nome }}</td>
                                <td class="py-2 text-slate-500">R$ {{ number_format($contrato->valor_mensal, 2, ',', '.') }}/mês</td>
                                <td class="py-2"><x-status-badge :status="$contrato->status" /></td>
                                <td class="py-2 text-right"><a href="{{ route('contratos.show', $contrato) }}" class="text-sky-700 hover:underline">Ver</a></td>
                            </tr>
                        @empty
                            <tr><td class="py-4 text-center text-slate-400">Nenhum contrato ainda.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-700">Dependentes</h3>
                    @can('create', \App\Models\Dependente::class)
                        <button type="button" onclick="document.getElementById('form-dependente').classList.toggle('hidden')" class="text-sm text-sky-700 hover:underline">+ adicionar</button>
                    @endcan
                </div>

                <form id="form-dependente" method="POST" action="{{ route('clientes.dependentes.store', $cliente) }}" class="mb-4 hidden grid grid-cols-2 gap-2 rounded-lg bg-slate-50 p-3">
                    @csrf
                    <input type="text" name="nome" placeholder="Nome" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input type="text" name="parentesco" placeholder="Parentesco" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input type="date" name="data_nascimento" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <button class="rounded-lg bg-sky-700 px-3 py-2 text-sm font-medium text-white">Salvar</button>
                </form>

                <ul class="divide-y divide-slate-50 text-sm">
                    @forelse ($cliente->dependentes as $dependente)
                        <li class="flex items-center justify-between py-2">
                            <span>{{ $dependente->nome }} <span class="text-slate-400">({{ $dependente->parentesco ?: 'dependente' }})</span></span>
                            <div class="flex items-center gap-3">
                                @if ($dependente->carteirinha)
                                    <a href="{{ route('carteirinhas.show', $dependente->carteirinha) }}" class="inline-flex items-center gap-1 text-xs text-sky-700 hover:underline">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M3 5h18a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm-2 5h22M5 15h4"/></svg>
                                        Carteirinha
                                    </a>
                                @else
                                    <span class="text-xs text-slate-400">Sem carteirinha</span>
                                @endif
                                <form method="POST" action="{{ route('clientes.dependentes.destroy', [$cliente, $dependente]) }}" onsubmit="return confirm('Remover este dependente?')">
                                    @csrf @method('DELETE')
                                    <button class="text-rose-600 hover:underline">remover</button>
                                </form>
                            </div>
                        </li>
                    @empty
                        <li class="py-4 text-center text-slate-400">Nenhum dependente cadastrado.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="space-y-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 text-center shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
                <h3 class="mb-3 text-sm font-semibold text-slate-700">Carteirinha digital</h3>
                @if ($cliente->carteirinha)
                    <x-carteirinha-card :carteirinha="$cliente->carteirinha" />
                    <a href="{{ route('carteirinhas.show', $cliente->carteirinha) }}" class="mt-3 inline-block text-xs text-sky-700 hover:underline">Ver carteirinha</a>
                @else
                    <p class="text-sm text-slate-400">Emitida automaticamente ao contratar um plano.</p>
                @endif
            </div>
        </div>
    </div>
@endsection
