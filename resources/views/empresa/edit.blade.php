@extends('layouts.app')

@section('titulo', 'Dados da empresa')

@section('conteudo')
    <form method="POST" action="{{ route('empresa.update') }}" enctype="multipart/form-data" class="max-w-3xl space-y-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-7">
        @csrf
        @method('PUT')

        <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Logo</label>
            <div class="flex items-center gap-4">
                <div class="grid h-20 w-20 shrink-0 place-items-center overflow-hidden rounded-xl border border-dashed border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-900">
                    @if ($empresa->logoUrl())
                        <img src="{{ $empresa->logoUrl() }}" alt="Logo atual" class="h-full w-full object-contain">
                    @else
                        <span class="text-xs text-gray-400">Sem logo</span>
                    @endif
                </div>
                <div>
                    <input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp" class="block text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-500 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-brand-600">
                    <p class="mt-1 text-xs text-slate-400">JPG, PNG ou WEBP · até 2MB. Aparece nos contratos, vouchers de entrada e relatórios em PDF.</p>
                    @error('logo')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 [&_input]:min-h-11 [&_input]:border-gray-300 [&_input]:bg-transparent [&_input]:text-gray-800 [&_input]:outline-none [&_input]:transition [&_input]:focus:border-brand-500 [&_input]:focus:ring-3 [&_input]:focus:ring-brand-500/10 dark:[&_input]:border-gray-700 dark:[&_input]:text-white/90">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">Nome</label>
                <input type="text" name="nome" value="{{ old('nome', $empresa->nome) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Razão social</label>
                <input type="text" name="razao_social" value="{{ old('razao_social', $empresa->razao_social) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">CNPJ</label>
                <input type="text" name="cnpj" value="{{ old('cnpj', $empresa->cnpj) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">E-mail</label>
                <input type="email" name="email" value="{{ old('email', $empresa->email) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Telefone</label>
                <input type="text" name="telefone" value="{{ old('telefone', $empresa->telefone) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">Endereço</label>
                <input type="text" name="endereco" value="{{ old('endereco', $empresa->configuracoes['endereco'] ?? '') }}" placeholder="Rua, número, bairro - cidade/UF" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-slate-400">Aparece no cabeçalho dos contratos.</p>
            </div>
        </div>

        @php $mp = $empresa->credenciaisMercadoPago(); $evo = $empresa->credenciaisEvolution(); @endphp

        <div class="border-t border-gray-100 pt-6 dark:border-gray-800">
            <h2 class="text-sm font-semibold text-slate-700">Mercado Pago (cobrança Pix)</h2>
            <p class="mt-1 text-xs text-slate-400">
                Usado pra gerar o QR Code Pix na compra online e no checkout. Se deixar em branco, o sistema usa a
                conta padrão da plataforma. Cadastre esta URL de notificação no painel do Mercado Pago (Suas
                integrações → Webhooks): <code class="rounded bg-gray-100 px-1.5 py-0.5 text-[11px] dark:bg-gray-800">{{ route('webhooks.mercadopago') }}</code>
            </p>
            <div class="mt-4 grid grid-cols-1 gap-5 sm:grid-cols-2 [&_input]:min-h-11 [&_input]:border-gray-300 [&_input]:bg-transparent [&_input]:text-gray-800 [&_input]:outline-none [&_input]:transition [&_input]:focus:border-brand-500 [&_input]:focus:ring-3 [&_input]:focus:ring-brand-500/10 dark:[&_input]:border-gray-700 dark:[&_input]:text-white/90">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-slate-700">Access Token</label>
                    <input type="password" name="mercadopago_access_token" value="{{ old('mercadopago_access_token') }}" autocomplete="off" placeholder="{{ $mp['access_token'] ? '••••••••••• (já configurado — deixe em branco pra manter)' : 'APP_USR-...' }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('mercadopago_access_token')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Public Key</label>
                    <input type="text" name="mercadopago_public_key" value="{{ old('mercadopago_public_key', $mp['public_key']) }}" autocomplete="off" placeholder="APP_USR-..." class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Webhook Secret</label>
                    <input type="password" name="mercadopago_webhook_secret" value="{{ old('mercadopago_webhook_secret') }}" autocomplete="off" placeholder="{{ $mp['webhook_secret'] ? '••••••••••• (já configurado — deixe em branco pra manter)' : 'Chave secreta do webhook' }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-slate-400">Painel do Mercado Pago → Webhooks → "Assinatura secreta".</p>
                </div>
            </div>
        </div>

        <div class="border-t border-gray-100 pt-6 dark:border-gray-800">
            <h2 class="text-sm font-semibold text-slate-700">Evolution API (WhatsApp)</h2>
            <p class="mt-1 text-xs text-slate-400">
                Usado pra régua de cobrança de mensalidade por WhatsApp. Se deixar em branco, o sistema usa a
                instância padrão da plataforma. Cadastre esta URL de webhook na instância:
                <code class="rounded bg-gray-100 px-1.5 py-0.5 text-[11px] dark:bg-gray-800">{{ route('webhooks.evolution') }}</code>
            </p>
            <div class="mt-4 grid grid-cols-1 gap-5 sm:grid-cols-2 [&_input]:min-h-11 [&_input]:border-gray-300 [&_input]:bg-transparent [&_input]:text-gray-800 [&_input]:outline-none [&_input]:transition [&_input]:focus:border-brand-500 [&_input]:focus:ring-3 [&_input]:focus:ring-brand-500/10 dark:[&_input]:border-gray-700 dark:[&_input]:text-white/90">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-slate-700">URL base da instância</label>
                    <input type="url" name="evolution_base_url" value="{{ old('evolution_base_url', $evo['base_url']) }}" placeholder="https://sua-evolution-api.com" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('evolution_base_url')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">API Key</label>
                    <input type="password" name="evolution_api_key" value="{{ old('evolution_api_key') }}" autocomplete="off" placeholder="{{ $evo['api_key'] ? '••••••••••• (já configurado — deixe em branco pra manter)' : 'Chave da instância' }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Nome da instância</label>
                    <input type="text" name="evolution_instance" value="{{ old('evolution_instance', $evo['instance']) }}" placeholder="ex: parque-aquatico" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button class="h-11 rounded-lg bg-brand-500 px-5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">Salvar alterações</button>
        </div>
    </form>
@endsection
