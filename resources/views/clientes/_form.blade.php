@php $c = $cliente ?? null; @endphp

<div class="grid grid-cols-1 gap-5 sm:grid-cols-2 [&_label]:text-gray-700 dark:[&_label]:text-gray-300 [&_input]:min-h-11 [&_input]:border-gray-300 [&_input]:bg-transparent [&_input]:text-gray-800 [&_input]:outline-none [&_input]:transition [&_input]:focus:border-brand-500 [&_input]:focus:ring-3 [&_input]:focus:ring-brand-500/10 dark:[&_input]:border-gray-700 dark:[&_input]:text-white/90 [&_select]:min-h-11 [&_select]:border-gray-300 [&_select]:bg-transparent [&_select]:text-gray-800 [&_select]:outline-none [&_select]:focus:border-brand-500 dark:[&_select]:border-gray-700 dark:[&_select]:bg-gray-900 dark:[&_select]:text-white/90 [&_textarea]:border-gray-300 [&_textarea]:bg-transparent [&_textarea]:text-gray-800 [&_textarea]:outline-none [&_textarea]:focus:border-brand-500 dark:[&_textarea]:border-gray-700 dark:[&_textarea]:text-white/90">
    <div class="sm:col-span-2">
        <label class="mb-2 block text-sm font-medium text-slate-700">Foto</label>
        <div class="flex items-center gap-4">
            <div class="grid h-16 w-16 shrink-0 place-items-center overflow-hidden rounded-full border border-dashed border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-900">
                @if ($c?->fotoUrl())
                    <img src="{{ $c->fotoUrl() }}" alt="Foto atual" class="h-full w-full object-cover">
                @else
                    <span class="text-xs text-gray-400">Sem foto</span>
                @endif
            </div>
            <div>
                <input type="file" name="foto" accept=".jpg,.jpeg,.png,.webp" class="block text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-500 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-brand-600">
                <p class="mt-1 text-xs text-slate-400">JPG, PNG ou WEBP · até 2MB. Aparece na carteirinha digital.</p>
                @error('foto')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
        </div>
    </div>

    <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-slate-700">Nome completo</label>
        <input type="text" name="nome" value="{{ old('nome', $c?->nome) }}" required
               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700">CPF</label>
        <input type="text" name="cpf" value="{{ old('cpf', $c?->cpf) }}" required
               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700">RG</label>
        <input type="text" name="rg" value="{{ old('rg', $c?->rg) }}"
               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700">Data de nascimento</label>
        <input type="date" name="data_nascimento" value="{{ old('data_nascimento', $c?->data_nascimento?->toDateString()) }}"
               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700">Unidade</label>
        <select name="unidade_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">—</option>
            @foreach ($unidades as $unidade)
                <option value="{{ $unidade->id }}" @selected(old('unidade_id', $c?->unidade_id) == $unidade->id)>{{ $unidade->nome }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700">E-mail</label>
        <input type="email" name="email" value="{{ old('email', $c?->email) }}"
               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700">Telefone</label>
        <input type="text" name="telefone" value="{{ old('telefone', $c?->telefone) }}"
               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700">WhatsApp (cobrança)</label>
        <input type="text" name="whatsapp" value="{{ old('whatsapp', $c?->whatsapp) }}" placeholder="Ex: 11999999999"
               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>

    @if ($c)
        <div>
            <label class="block text-sm font-medium text-slate-700">Status</label>
            <select name="status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="ativo" @selected(old('status', $c->status) === 'ativo')>Ativo</option>
                <option value="inativo" @selected(old('status', $c->status) === 'inativo')>Inativo</option>
                <option value="bloqueado" @selected(old('status', $c->status) === 'bloqueado')>Bloqueado</option>
            </select>
        </div>
    @endif

    <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-slate-700">CEP</label>
        <input type="text" id="campo-cep" name="cep" value="{{ old('cep', $c?->cep) }}" inputmode="numeric" maxlength="9" autocomplete="postal-code"
               class="mt-1 w-full max-w-xs rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <p id="cep-feedback" class="mt-1 text-xs text-slate-400"></p>
    </div>

    <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-slate-700">Endereço</label>
        <input type="text" id="campo-endereco" name="endereco" value="{{ old('endereco', $c?->endereco) }}"
               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700">Número</label>
        <input type="text" id="campo-numero" name="numero" value="{{ old('numero', $c?->numero) }}"
               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700">Bairro</label>
        <input type="text" id="campo-bairro" name="bairro" value="{{ old('bairro', $c?->bairro) }}"
               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700">Cidade</label>
        <input type="text" id="campo-cidade" name="cidade" value="{{ old('cidade', $c?->cidade) }}"
               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700">UF</label>
        <input type="text" id="campo-uf" maxlength="2" name="uf" value="{{ old('uf', $c?->uf) }}"
               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase">
    </div>

    <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-slate-700">Observações</label>
        <textarea name="observacoes" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('observacoes', $c?->observacoes) }}</textarea>
    </div>
</div>

<script>
    (function () {
        const campoCep = document.getElementById('campo-cep');
        const feedback = document.getElementById('cep-feedback');
        const campos = {
            endereco: document.getElementById('campo-endereco'),
            bairro: document.getElementById('campo-bairro'),
            cidade: document.getElementById('campo-cidade'),
            uf: document.getElementById('campo-uf'),
        };

        let ultimoCepBuscado = null;

        function buscarCep() {
            const cep = campoCep.value.replace(/\D/g, '');

            if (cep.length !== 8 || cep === ultimoCepBuscado) return;
            ultimoCepBuscado = cep;

            feedback.textContent = 'Buscando endereço...';
            feedback.className = 'mt-1 text-xs text-slate-400';

            fetch('https://viacep.com.br/ws/' + cep + '/json/')
                .then((resposta) => resposta.json())
                .then((dados) => {
                    if (dados.erro) {
                        feedback.textContent = 'CEP não encontrado.';
                        feedback.className = 'mt-1 text-xs text-rose-500';
                        return;
                    }

                    campos.endereco.value = dados.logradouro || campos.endereco.value;
                    campos.bairro.value = dados.bairro || campos.bairro.value;
                    campos.cidade.value = dados.localidade || campos.cidade.value;
                    campos.uf.value = dados.uf || campos.uf.value;

                    feedback.textContent = 'Endereço preenchido automaticamente.';
                    feedback.className = 'mt-1 text-xs text-emerald-600';
                    document.getElementById('campo-numero')?.focus();
                })
                .catch(() => {
                    feedback.textContent = 'Não foi possível buscar o CEP agora.';
                    feedback.className = 'mt-1 text-xs text-rose-500';
                });
        }

        campoCep.addEventListener('blur', buscarCep);
        campoCep.addEventListener('input', function () {
            if (campoCep.value.replace(/\D/g, '').length === 8) buscarCep();
        });
    })();
</script>
