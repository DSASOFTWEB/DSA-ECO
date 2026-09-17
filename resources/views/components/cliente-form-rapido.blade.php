@props(['unidades'])

<div class="grid gap-4 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <x-input label="Nome completo" name="nome" id="cliente-rapido-nome" required x-model="novoCliente.nome" />
        <p x-show="clienteErros.nome" x-text="clienteErros.nome?.[0]" class="mt-1 text-xs text-rose-600"></p>
    </div>

    <div>
        <x-input label="CPF" name="cpf" id="cliente-rapido-cpf" required x-model="novoCliente.cpf" />
        <p x-show="clienteErros.cpf" x-text="clienteErros.cpf?.[0]" class="mt-1 text-xs text-rose-600"></p>
    </div>

    <div>
        <x-input label="Data de nascimento" name="data_nascimento" id="cliente-rapido-nascimento" type="date" x-model="novoCliente.data_nascimento" />
        <p x-show="clienteErros.data_nascimento" x-text="clienteErros.data_nascimento?.[0]" class="mt-1 text-xs text-rose-600"></p>
    </div>

    <div>
        <x-input label="Telefone" name="telefone" id="cliente-rapido-telefone" x-model="novoCliente.telefone" />
        <p x-show="clienteErros.telefone" x-text="clienteErros.telefone?.[0]" class="mt-1 text-xs text-rose-600"></p>
    </div>

    <div>
        <x-input label="WhatsApp" name="whatsapp" id="cliente-rapido-whatsapp" x-model="novoCliente.whatsapp" />
        <p x-show="clienteErros.whatsapp" x-text="clienteErros.whatsapp?.[0]" class="mt-1 text-xs text-rose-600"></p>
    </div>

    <div class="sm:col-span-2">
        <x-select label="Unidade" name="unidade_id" id="cliente-rapido-unidade" x-model="novoCliente.unidade_id">
            <option value="">Sem unidade específica</option>
            @foreach ($unidades as $unidade)
                <option value="{{ $unidade->id }}">{{ $unidade->nome }}</option>
            @endforeach
        </x-select>
        <p x-show="clienteErros.unidade_id" x-text="clienteErros.unidade_id?.[0]" class="mt-1 text-xs text-rose-600"></p>
    </div>
</div>
