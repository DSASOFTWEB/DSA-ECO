@extends('layouts.app')

@section('titulo', 'Editar contrato '.$contrato->numero_contrato)

@section('conteudo')
    <form method="POST" action="{{ route('contratos.update', $contrato) }}" class="max-w-3xl space-y-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-7">
        @csrf
        @method('PUT')

        <div>
            <p class="text-sm text-slate-500">Cliente</p>
            <p class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $contrato->cliente->nome }}</p>
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 [&_label]:text-gray-700 dark:[&_label]:text-gray-300 [&_input]:min-h-11 [&_input]:border-gray-300 [&_input]:bg-transparent [&_input]:text-gray-800 [&_input]:outline-none [&_input]:focus:border-brand-500 dark:[&_input]:border-gray-700 dark:[&_input]:text-white/90 [&_select]:min-h-11 [&_select]:border-gray-300 [&_select]:bg-transparent [&_select]:text-gray-800 [&_select]:outline-none [&_select]:focus:border-brand-500 dark:[&_select]:border-gray-700 dark:[&_select]:bg-gray-900 dark:[&_select]:text-white/90">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium">Plano</label>
                <select name="plano_id" required class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
                    @foreach ($planos as $plano)
                        <option value="{{ $plano->id }}" @selected(old('plano_id', $contrato->plano_id) == $plano->id)>
                            {{ $plano->nome }} — R$ {{ number_format($plano->valor, 2, ',', '.') }}
                        </option>
                    @endforeach
                </select>
                @error('plano_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-slate-400">Ao trocar o plano, o valor mensal padrão do novo plano é aplicado (a menos que você informe outro valor abaixo).</p>
            </div>

            <div>
                <label class="block text-sm font-medium">Dia de vencimento</label>
                <input type="number" min="1" max="31" name="dia_vencimento" value="{{ old('dia_vencimento', $contrato->dia_vencimento) }}" required class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-slate-400">1 a 31. Em meses sem esse dia, usa o último dia do mês.</p>
                @error('dia_vencimento')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-slate-400">Atualiza o vencimento das mensalidades ainda abertas (pendente/atrasado).</p>
            </div>

            <div>
                <label class="block text-sm font-medium">Valor mensal</label>
                <input type="number" step="0.01" min="0" name="valor_mensal" value="{{ old('valor_mensal', $contrato->valor_mensal) }}" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
                @error('valor_mensal')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium">Caução / entrada</label>
                <input type="number" step="0.01" min="0" name="valor_caucao" value="{{ old('valor_caucao', $contrato->valor_caucao) }}" required class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
                @error('valor_caucao')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-slate-400">0 = sem entrada. Só altera valor enquanto a caução não estiver paga.</p>
            </div>

            <div>
                <label class="block text-sm font-medium">Data da primeira mensalidade</label>
                <input type="date" name="primeiro_vencimento" value="{{ old('primeiro_vencimento', $contrato->primeiro_vencimento?->toDateString()) }}" required class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
                @error('primeiro_vencimento')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-slate-400">Só altera a primeira mensalidade enquanto ela estiver aberta.</p>
            </div>

            <div>
                <label class="block text-sm font-medium">Desconto (%)</label>
                <input type="number" step="0.01" min="0" max="100" name="desconto_percentual" value="{{ old('desconto_percentual', $contrato->desconto_percentual) }}" class="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
                @error('desconto_percentual')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('contratos.show', $contrato) }}" class="inline-flex h-11 items-center rounded-lg border border-gray-300 px-5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Cancelar</a>
            <button class="h-11 rounded-lg bg-brand-500 px-5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">Salvar alterações</button>
        </div>
    </form>
@endsection
