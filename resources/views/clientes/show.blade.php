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
                @if ($cliente->contratoAtivo)
                    <p class="mt-1 text-xs text-slate-400">
                        Contrato ativo: {{ $cliente->contratoAtivo->plano?->nome }}
                        · {{ $cliente->contratoAtivo->numero_contrato }}
                    </p>
                @endif
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($cliente->carteirinha)
                @can('view', $cliente->carteirinha)
                    <a href="{{ route('carteirinhas.imprimir', $cliente->carteirinha) }}" target="_blank" class="rounded-lg border border-sky-300 bg-sky-50 px-4 py-2 text-sm font-medium text-sky-800 hover:bg-sky-100">Imprimir carteirinha</a>
                @endcan
            @endif
            @if ($cliente->contratoAtivo)
                @can('view', $cliente->contratoAtivo)
                    <a href="{{ route('contratos.show', $cliente->contratoAtivo) }}" class="rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-800 hover:bg-emerald-100">Ver contrato</a>
                    <a href="{{ route('contratos.pdf', $cliente->contratoAtivo) }}" target="_blank" class="rounded-lg border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Contrato em PDF</a>
                @endcan
            @endif
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
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold text-slate-700">Contratos</h3>
                    @if (! $cliente->contratoAtivo)
                        @can('create', \App\Models\Contrato::class)
                            <a href="{{ route('contratos.create', ['cliente_id' => $cliente->id]) }}" class="text-sm text-sky-700 hover:underline">+ Novo contrato</a>
                        @endcan
                    @endif
                </div>
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase text-slate-400">
                        <tr>
                            <th class="py-2">Plano</th>
                            <th class="py-2">Número</th>
                            <th class="py-2">Valor</th>
                            <th class="py-2">Status</th>
                            <th class="py-2 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @forelse ($cliente->contratos as $contrato)
                            <tr>
                                <td class="py-2">{{ $contrato->plano->nome }}</td>
                                <td class="py-2 text-slate-500">{{ $contrato->numero_contrato }}</td>
                                <td class="py-2 text-slate-500">R$ {{ number_format($contrato->valor_mensal, 2, ',', '.') }}/mês</td>
                                <td class="py-2"><x-status-badge :status="$contrato->status" /></td>
                                <td class="py-2 text-right">
                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                        <a href="{{ route('contratos.show', $contrato) }}" class="text-sky-700 hover:underline">Abrir</a>
                                        <a href="{{ route('contratos.pdf', $contrato) }}" target="_blank" class="text-slate-600 hover:underline">PDF</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-4 text-center text-slate-400">Nenhum contrato ainda.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
                <h3 class="mb-3 text-sm font-semibold text-slate-700">Histórico de pagamentos</h3>
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase text-slate-400">
                        <tr>
                            <th class="py-2">Competência</th>
                            <th class="py-2">Vencimento</th>
                            <th class="py-2">Pagamento</th>
                            <th class="py-2">Valor</th>
                            <th class="py-2">Status</th>
                            <th class="py-2 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @forelse ($historicoPagamentos as $mensalidade)
                            <tr>
                                <td class="py-2">
                                    <div>{{ $mensalidade->ehCaucao() ? 'Caução / entrada' : $mensalidade->competencia->format('m/Y') }}</div>
                                    <div class="text-xs text-slate-400">{{ $mensalidade->contrato?->plano?->nome }}</div>
                                </td>
                                <td class="py-2 text-slate-500">{{ $mensalidade->data_vencimento->format('d/m/Y') }}</td>
                                <td class="py-2 text-slate-500">
                                    {{ $mensalidade->data_pagamento?->format('d/m/Y') ?: '—' }}
                                    @if ($mensalidade->forma_pagamento)
                                        <span class="block text-xs text-slate-400">{{ $mensalidade->forma_pagamento }}</span>
                                    @endif
                                </td>
                                <td class="py-2 text-slate-500">R$ {{ number_format($mensalidade->valor_total, 2, ',', '.') }}</td>
                                <td class="py-2"><x-status-badge :status="$mensalidade->status" /></td>
                                <td class="py-2 text-right">
                                    <a href="{{ route('mensalidades.show', $mensalidade) }}" class="text-sky-700 hover:underline">Ver</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-6 text-center text-slate-400">Nenhuma mensalidade gerada ainda.</td></tr>
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

                @php
                    $campoDep = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 dark:border-gray-700 dark:bg-gray-800 dark:text-white/90';
                    $errosAdicionar = $errors->hasAny(['nome', 'parentesco', 'data_nascimento', 'cpf']);
                @endphp
                <form id="form-dependente" method="POST" action="{{ route('clientes.dependentes.store', $cliente) }}" class="mb-4 {{ $errosAdicionar ? '' : 'hidden' }} grid grid-cols-2 gap-2 rounded-lg bg-slate-50 p-3 dark:bg-white/[0.03]">
                    @csrf
                    <input type="text" name="nome" value="{{ $errosAdicionar ? old('nome') : '' }}" placeholder="Nome completo" required class="col-span-2 {{ $campoDep }}">
                    <select name="parentesco" required class="{{ $campoDep }}">
                        <option value="">Parentesco</option>
                        @foreach (\App\Models\Dependente::niveisParentesco() as $nivel)
                            <option value="{{ $nivel }}" @selected($errosAdicionar && old('parentesco') === $nivel)>{{ $nivel }}</option>
                        @endforeach
                    </select>
                    <input type="date" name="data_nascimento" value="{{ $errosAdicionar ? old('data_nascimento') : '' }}" required title="Data de nascimento" class="{{ $campoDep }}">
                    <input type="text" name="cpf" value="{{ $errosAdicionar ? old('cpf') : '' }}" placeholder="CPF (opcional)" inputmode="numeric" class="{{ $campoDep }}">
                    <button class="rounded-lg bg-sky-700 px-3 py-2 text-sm font-medium text-white hover:bg-sky-800">Salvar</button>
                    @foreach (['nome', 'parentesco', 'data_nascimento', 'cpf'] as $campoErro)
                        @error($campoErro)<p class="col-span-2 text-xs text-rose-600">{{ $message }}</p>@enderror
                    @endforeach
                </form>

                <ul class="divide-y divide-slate-100 text-sm dark:divide-gray-800">
                    @forelse ($cliente->dependentes as $dependente)
                        @php
                            // Erro ao salvar a edição deste dependente: reabre o formulário dele com o que foi digitado.
                            $bagEdicao = $errors->getBag('dependente'.$dependente->id);
                            $comErro = $bagEdicao->any();
                            $valor = fn (string $campo, $atual) => $comErro ? old($campo) : $atual;
                        @endphp
                        <li class="py-2" x-data="{ editando: @js($comErro) }">
                            <div class="flex items-center justify-between gap-3">
                                <span>
                                    {{ $dependente->nome }} <span class="text-slate-400">({{ $dependente->parentesco ?: 'dependente' }})</span>
                                    <span class="block text-xs text-slate-400">
                                        {{ $dependente->data_nascimento?->format('d/m/Y') }}@if ($dependente->data_nascimento) · {{ $dependente->data_nascimento->age }} anos @endif
                                        @if ($dependente->cpf) · CPF {{ $dependente->cpf }} @endif
                                    </span>
                                </span>
                                <div class="flex shrink-0 items-center gap-3">
                                    @if ($dependente->carteirinha)
                                        <a href="{{ route('carteirinhas.imprimir', $dependente->carteirinha) }}" target="_blank" class="text-xs text-sky-700 hover:underline">Imprimir</a>
                                        <a href="{{ route('carteirinhas.show', $dependente->carteirinha) }}" class="inline-flex items-center gap-1 text-xs text-sky-700 hover:underline">Ver</a>
                                    @else
                                        <span class="text-xs text-slate-400">Sem carteirinha</span>
                                    @endif
                                    @can('update', $dependente)
                                        <button type="button" @click="editando = ! editando" class="text-xs font-semibold text-amber-700 hover:underline dark:text-amber-400" x-text="editando ? 'cancelar' : 'editar'">editar</button>
                                    @endcan
                                    @can('delete', $dependente)
                                        <form method="POST" action="{{ route('clientes.dependentes.destroy', [$cliente, $dependente]) }}" onsubmit="return confirm('Remover este dependente?')">
                                            @csrf @method('DELETE')
                                            <button class="text-xs text-rose-600 hover:underline">remover</button>
                                        </form>
                                    @endcan
                                </div>
                            </div>

                            @can('update', $dependente)
                                <form x-show="editando" x-cloak method="POST" action="{{ route('clientes.dependentes.update', [$cliente, $dependente]) }}" class="mt-2 grid grid-cols-2 gap-2 rounded-lg bg-amber-50/60 p-3 dark:bg-amber-500/5">
                                    @csrf @method('PUT')
                                    <input type="text" name="nome" value="{{ $valor('nome', $dependente->nome) }}" placeholder="Nome completo" required class="col-span-2 {{ $campoDep }}">
                                    @php
                                        // Cadastro antigo ("ESPOSA", "FILHA"...) abre já na opção equivalente da lista.
                                        $parentescoAtual = $valor('parentesco', $dependente->parentescoNaLista() ?? $dependente->parentesco);
                                        $legado = $dependente->parentescoNaLista() === null && filled($dependente->parentesco);
                                    @endphp
                                    <select name="parentesco" required class="{{ $campoDep }}">
                                        @if ($legado)
                                            <option value="{{ $dependente->parentesco }}" @selected($parentescoAtual === $dependente->parentesco)>{{ $dependente->parentesco }} (atual)</option>
                                        @endif
                                        @foreach (\App\Models\Dependente::niveisParentesco() as $nivel)
                                            <option value="{{ $nivel }}" @selected($parentescoAtual === $nivel)>{{ $nivel }}</option>
                                        @endforeach
                                    </select>
                                    <input type="date" name="data_nascimento" value="{{ $valor('data_nascimento', $dependente->data_nascimento?->toDateString()) }}" required title="Data de nascimento" class="{{ $campoDep }}">
                                    <input type="text" name="cpf" value="{{ $valor('cpf', $dependente->cpf) }}" placeholder="CPF (opcional)" inputmode="numeric" class="{{ $campoDep }}">
                                    <button class="rounded-lg bg-amber-600 px-3 py-2 text-sm font-medium text-white hover:bg-amber-700">Salvar alterações</button>
                                    @foreach ($bagEdicao->all() as $mensagem)
                                        <p class="col-span-2 text-xs text-rose-600">{{ $mensagem }}</p>
                                    @endforeach
                                </form>
                            @endcan
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
                    <div class="mt-3 flex flex-col items-center gap-2">
                        <a href="{{ route('carteirinhas.imprimir', $cliente->carteirinha) }}" target="_blank" class="inline-flex rounded-lg bg-sky-700 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-800">Imprimir carteirinha</a>
                        <a href="{{ route('carteirinhas.show', $cliente->carteirinha) }}" class="text-xs text-sky-700 hover:underline">Ver detalhes da carteirinha</a>
                    </div>
                @else
                    <p class="text-sm text-slate-400">Emitida automaticamente ao contratar um plano.</p>
                @endif
            </div>

            @if ($cliente->contratoAtivo)
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
                    <h3 class="mb-3 text-sm font-semibold text-slate-700">Contrato atual</h3>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-slate-400">Número</dt><dd>{{ $cliente->contratoAtivo->numero_contrato }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-400">Plano</dt><dd>{{ $cliente->contratoAtivo->plano?->nome }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-400">Valor</dt><dd>R$ {{ number_format($cliente->contratoAtivo->valor_mensal, 2, ',', '.') }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-slate-400">Vencimento</dt><dd>dia {{ $cliente->contratoAtivo->dia_vencimento }}</dd></div>
                    </dl>
                    <div class="mt-4 flex flex-col gap-2">
                        <a href="{{ route('contratos.show', $cliente->contratoAtivo) }}" class="rounded-lg bg-emerald-600 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-emerald-700">Ir para o contrato</a>
                        <a href="{{ route('contratos.pdf', $cliente->contratoAtivo) }}" target="_blank" class="rounded-lg border border-slate-300 px-4 py-2 text-center text-sm hover:bg-slate-50">Abrir PDF do contrato</a>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
