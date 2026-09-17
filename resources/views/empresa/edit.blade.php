@extends('layouts.app')

@section('titulo', 'Dados da empresa')

@section('conteudo')
    <form method="POST" action="{{ route('empresa.update') }}" enctype="multipart/form-data" class="max-w-5xl space-y-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-7">
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
                <input type="text" name="nome" id="empresa-nome" value="{{ old('nome', $empresa->nome) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Razão social</label>
                <input type="text" name="razao_social" id="empresa-razao-social" value="{{ old('razao_social', $empresa->razao_social) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div x-data="consultaCnpjForm({ url: @js(route('empresa.consultar-cnpj')), cnpj: @js(old('cnpj', $empresa->cnpj)) })">
                <label class="block text-sm font-medium text-slate-700">CNPJ</label>
                <div class="mt-1 flex gap-2">
                    <input type="text" name="cnpj" id="empresa-cnpj" x-model="cnpj" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="00.000.000/0001-00">
                    <button type="button" @click="consultar()" :disabled="consultando" class="shrink-0 rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium hover:bg-slate-50 disabled:opacity-50 dark:border-gray-700 dark:hover:bg-white/5">
                        <span x-text="consultando ? 'Buscando…' : 'Consultar'"></span>
                    </button>
                </div>
                <p class="mt-1 text-xs" :class="erro ? 'text-rose-600' : 'text-slate-400'" x-text="erro || msg || 'Consulta automática preenche razão social, IE, endereço e IBGE.'"></p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Inscrição estadual (IE)</label>
                <input type="text" name="ie" id="empresa-ie" value="{{ old('ie', $empresa->ie) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Inscrição municipal (IM)</label>
                <input type="text" name="im" value="{{ old('im', $empresa->im) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">CNAE</label>
                <input type="text" name="cnae" id="empresa-cnae" value="{{ old('cnae', $empresa->cnae) }}" maxlength="7" inputmode="numeric" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Regime tributário</label>
                <select name="regime_tributario" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @foreach (\App\Models\Empresa::regimesTributarios() as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected(old('regime_tributario', $empresa->regime_tributario ?? 'simples') === $valor)>{{ $rotulo }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Autorizador XML (CNPJ)</label>
                <input type="text" name="aut_xml" value="{{ old('aut_xml', $empresa->aut_xml) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Opcional">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">E-mail</label>
                <input type="email" name="email" id="empresa-email" value="{{ old('email', $empresa->email) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Telefone</label>
                <input type="text" name="telefone" id="empresa-telefone" value="{{ old('telefone', $empresa->telefone) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">Endereço</label>
                <input type="text" name="endereco" id="empresa-endereco" value="{{ old('endereco', $empresa->configuracoes['endereco'] ?? '') }}" placeholder="Rua, número, bairro - cidade/UF" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
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

        @php $imp = $empresa->configuracaoImpressao(); @endphp

        <div class="border-t border-gray-100 pt-6 dark:border-gray-800">
            <h2 class="text-sm font-semibold text-slate-700">Fiscal — NF-e / NFC-e / NFS-e</h2>
            <p class="mt-1 text-xs text-slate-400">
                Parâmetros do emitente (mesmo modelo do Evora). Tokens em branco mantêm o valor salvo;
                Cosmos e tokens de NFS-e/IBPT sem valor na empresa usam o fallback do <code class="rounded bg-gray-100 px-1 dark:bg-gray-800">.env</code>.
            </p>

            <div class="mt-4 grid grid-cols-1 gap-5 sm:grid-cols-3 [&_input]:min-h-11 [&_input]:border-gray-300 [&_input]:bg-transparent [&_input]:text-gray-800 [&_input]:outline-none [&_input]:transition [&_input]:focus:border-brand-500 [&_input]:focus:ring-3 [&_input]:focus:ring-brand-500/10 dark:[&_input]:border-gray-700 dark:[&_input]:text-white/90 [&_select]:min-h-11 [&_select]:border-gray-300 [&_select]:bg-transparent">
            <div
                class="relative"
                x-data="buscaCidadeIbge({
                    url: @js(route('cidades.autocomplete')),
                    codigo: @js(old('codigo_municipio_ibge', $empresa->codigo_municipio_ibge)),
                })"
            >
                <label class="block text-sm font-medium text-slate-700">Cód. município IBGE</label>
                <input
                    type="text"
                    name="codigo_municipio_ibge"
                    id="empresa-ibge"
                    x-model="codigo"
                    @input.debounce.300ms="buscar()"
                    @focus="buscar()"
                    maxlength="80"
                    autocomplete="off"
                    class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    placeholder="3550308 ou nome da cidade"
                >
                <p class="mt-1 text-xs text-slate-400">
                    Obrigatório para emitir NFC-e / NFS-e.
                    <a href="{{ route('cidades.index') }}" class="text-brand-600 hover:underline">Gerenciar cidades</a>
                </p>
                <ul
                    x-cloak
                    x-show="aberta && itens.length"
                    class="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-900"
                >
                    <template x-for="item in itens" :key="item.codigo">
                        <li>
                            <button
                                type="button"
                                class="block w-full px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-white/5"
                                @click="selecionar(item)"
                                x-text="item.text"
                            ></button>
                        </li>
                    </template>
                </ul>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">LC 116 hospedagem</label>
                <input type="text" name="codigo_servico_hospedagem_lc116" value="{{ old('codigo_servico_hospedagem_lc116', $empresa->codigo_servico_hospedagem_lc116) }}" maxlength="10" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="9.01">
                <p class="mt-1 text-xs text-slate-400">Código do serviço de diária na NFS-e Nacional.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Ambiente SEFAZ</label>
                    <select name="ambiente_nfe" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="2" @selected((int) old('ambiente_nfe', $empresa->ambiente_nfe ?? 2) === 2)>Homologação</option>
                        <option value="1" @selected((int) old('ambiente_nfe', $empresa->ambiente_nfe ?? 2) === 1)>Produção</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">CSC (NFC-e)</label>
                    <input type="password" name="csc" value="{{ old('csc') }}" autocomplete="off" placeholder="{{ $empresa->csc ? '•••••••• (já configurado)' : 'Código CSC' }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">CSC ID / idToken</label>
                    <input type="text" name="csc_id" value="{{ old('csc_id', $empresa->csc_id) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700">Série NF-e</label>
                    <input type="number" min="1" name="numero_serie_nfe" value="{{ old('numero_serie_nfe', $empresa->numero_serie_nfe ?? 1) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Últ. NF-e produção</label>
                    <input type="number" min="0" name="numero_ultima_nfe_producao" value="{{ old('numero_ultima_nfe_producao', $empresa->numero_ultima_nfe_producao ?? 0) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Últ. NF-e homologação</label>
                    <input type="number" min="0" name="numero_ultima_nfe_homologacao" value="{{ old('numero_ultima_nfe_homologacao', $empresa->numero_ultima_nfe_homologacao ?? 0) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700">Série NFC-e</label>
                    <input type="number" min="1" name="numero_serie_nfce" value="{{ old('numero_serie_nfce', $empresa->numero_serie_nfce ?? 1) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Últ. NFC-e produção</label>
                    <input type="number" min="0" name="numero_ultima_nfce_producao" value="{{ old('numero_ultima_nfce_producao', $empresa->numero_ultima_nfce_producao ?? 0) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Últ. NFC-e homologação</label>
                    <input type="number" min="0" name="numero_ultima_nfce_homologacao" value="{{ old('numero_ultima_nfce_homologacao', $empresa->numero_ultima_nfce_homologacao ?? 0) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700">Provedor NFS-e</label>
                    <select name="nfse_provider" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="nacional_gov" @selected(old('nfse_provider', $empresa->nfse_provider ?? 'nacional_gov') === 'nacional_gov')>NFS-e Nacional (Gov.br)</option>
                        <option value="integranotas" @selected(old('nfse_provider', $empresa->nfse_provider ?? '') === 'integranotas')>Integra Notas / CloudDFe</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Série NFS-e / DPS</label>
                    <input type="number" min="1" name="numero_serie_nfse" value="{{ old('numero_serie_nfse', $empresa->numero_serie_nfse ?? 1) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Últ. NFS-e / DPS</label>
                    <input type="number" min="0" name="numero_ultima_nfse" value="{{ old('numero_ultima_nfse', $empresa->numero_ultima_nfse ?? 0) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>

                <div class="sm:col-span-3">
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm dark:border-gray-800 dark:bg-white/[0.03]">
                        <input type="checkbox" name="nfse_nacional_habilitado" value="1" class="mt-0.5 h-4 w-4 rounded border-gray-300 text-brand-600" @checked(old('nfse_nacional_habilitado', $empresa->nfse_nacional_habilitado))>
                        <span>
                            <span class="font-medium text-slate-700">Habilitar emissão NFS-e Nacional</span>
                            <span class="mt-0.5 block text-xs text-slate-400">Usa o certificado A1 abaixo (mesmo padrão Evora / Nacional GOV).</span>
                        </span>
                    </label>
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-slate-700">Token NFS-e (Integra Notas)</label>
                    <input type="password" name="token_nfse" value="{{ old('token_nfse') }}" autocomplete="off" placeholder="{{ $empresa->token_nfse ? '•••••••• (já configurado)' : 'Vazio = TOKEN_NFSE do .env' }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Token IBPT</label>
                    <input type="password" name="token_ibpt" value="{{ old('token_ibpt') }}" autocomplete="off" placeholder="{{ $empresa->token_ibpt ? '••••••••' : 'Vazio = TOKEN_IBPT do .env' }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium text-slate-700">Token Bluesoft Cosmos (EAN)</label>
                    <input type="password" name="bluesoft_token" value="{{ old('bluesoft_token') }}" autocomplete="off" placeholder="{{ $empresa->bluesoft_token ? '•••••••• (já configurado na empresa)' : 'Vazio = COSMOS_TOKEN do .env' }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-slate-400">Prioridade: token da empresa → <code class="rounded bg-gray-100 px-1 dark:bg-gray-800">COSMOS_TOKEN</code> da plataforma.</p>
                </div>

                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium text-slate-700">Observação padrão NF-e</label>
                    <input type="text" name="observacao_padrao_nfe" value="{{ old('observacao_padrao_nfe', $empresa->observacao_padrao_nfe) }}" maxlength="500" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div class="sm:col-span-3">
                    <label class="block text-sm font-medium text-slate-700">Observação padrão NFC-e</label>
                    <input type="text" name="observacao_padrao_nfce" value="{{ old('observacao_padrao_nfce', $empresa->observacao_padrao_nfce) }}" maxlength="500" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>
        </div>

        <div class="border-t border-gray-100 pt-6 dark:border-gray-800">
            <h2 class="text-sm font-semibold text-slate-700">Certificado digital A1</h2>
            <p class="mt-1 text-xs text-slate-400">
                Arquivo <strong>.pfx / .p12 / .bin</strong> armazenado no banco e no storage privado persistente. Senha gravada criptografada.
                @if ($empresa->temCertificadoDigital())
                    <span class="text-emerald-600">Certificado já cadastrado{{ $empresa->certificado_validade ? ' · validade '.$empresa->certificado_validade->format('d/m/Y') : '' }}.</span>
                    <a href="{{ route('empresa.certificado.download') }}" class="ml-2 text-sky-600 hover:underline">Baixar .pfx</a>
                    <form method="POST" action="{{ route('empresa.certificado.destroy') }}" class="ml-2 inline" onsubmit="return confirm('Remover o certificado digital e a senha cadastrada?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-rose-600 hover:underline">Remover certificado</button>
                    </form>
                @else
                    <span class="text-amber-600">Nenhum certificado enviado ainda.</span>
                @endif
            </p>
            <div class="mt-4 grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Arquivo do certificado</label>
                    <input type="file" name="certificado" accept=".pfx,.p12,.bin,application/x-pkcs12" class="mt-1 block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-500 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white">
                    @error('certificado')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Senha do certificado</label>
                    <input type="password" name="certificado_senha" value="{{ old('certificado_senha') }}" autocomplete="new-password" placeholder="{{ $empresa->temCertificadoDigital() ? '•••••••• (deixe em branco pra manter)' : 'Senha do .pfx' }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('certificado_senha')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="border-t border-gray-100 pt-6 dark:border-gray-800">
            <h2 class="text-sm font-semibold text-slate-700">Impressão do cupom (PDV)</h2>
            <p class="mt-1 text-xs text-slate-400">
                Define como o comprovante de venda é impresso após finalizar no PDV.
                <strong>DOM 80mm</strong> usa a impressora do navegador; <strong>ESC/POS</strong> envia bytes
                direto pra impressora térmica (agente local ou Web Serial).
            </p>

            <div class="mt-4 grid grid-cols-1 gap-5 sm:grid-cols-2 [&_input]:min-h-11 [&_input]:border-gray-300 [&_input]:bg-white [&_input]:text-gray-900 [&_input]:outline-none [&_input]:transition [&_input]:focus:border-brand-500 [&_input]:focus:ring-3 [&_input]:focus:ring-brand-500/10 dark:[&_input]:border-gray-700 dark:[&_input]:bg-gray-900 dark:[&_input]:text-white">
                <div class="sm:col-span-2">
                    <label class="form-label">Modo de impressão</label>
                    <select name="impressao_modo" class="form-control mt-1">
                        <option value="dom" @selected(old('impressao_modo', $imp['modo']) === 'dom')>Somente DOM 80mm (navegador)</option>
                        <option value="escpos" @selected(old('impressao_modo', $imp['modo']) === 'escpos')>Somente ESC/POS (térmica)</option>
                        <option value="ambos" @selected(old('impressao_modo', $imp['modo']) === 'ambos')>Ambos (padrão destacado conforme escolha)</option>
                    </select>
                    @error('impressao_modo')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="form-label">Colunas ESC/POS</label>
                    <select name="impressao_colunas" class="form-control mt-1">
                        <option value="48" @selected((int) old('impressao_colunas', $imp['colunas']) === 48)>48 — bobina 80mm</option>
                        <option value="42" @selected((int) old('impressao_colunas', $imp['colunas']) === 42)>42 — bobina ~72mm</option>
                        <option value="40" @selected((int) old('impressao_colunas', $imp['colunas']) === 40)>40 — bobina ~72mm</option>
                        <option value="32" @selected((int) old('impressao_colunas', $imp['colunas']) === 32)>32 — bobina 58mm</option>
                    </select>
                    @error('impressao_colunas')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="form-label">URL do agente ESC/POS</label>
                    <input type="url" name="impressao_agente_url" value="{{ old('impressao_agente_url', $imp['agente_url']) }}" placeholder="http://127.0.0.1:9110" class="form-control mt-1">
                    <p class="mt-1 text-xs text-slate-400">Serviço local na máquina do caixa (POST /print). Deixe o padrão se usar Web Serial ou download .bin.</p>
                    @error('impressao_agente_url')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div class="sm:col-span-2">
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm dark:border-gray-800 dark:bg-white/[0.03]">
                        <input type="checkbox" name="impressao_auto_imprimir" value="1" class="mt-0.5 h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500" @checked(old('impressao_auto_imprimir', $imp['auto_imprimir']))>
                        <span>
                            <span class="font-medium text-slate-700 dark:text-white/90">Imprimir automaticamente ao abrir o comprovante</span>
                            <span class="mt-0.5 block text-xs text-slate-400">Dispara o modo escolhido assim que a tela do cupom carrega (útil no fluxo do PDV).</span>
                        </span>
                    </label>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button class="h-11 rounded-lg bg-brand-500 px-5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">Salvar alterações</button>
        </div>
    </form>

    @can('create', App\Models\PontoAtendimento::class)
    @if($unidades->isNotEmpty())
    <section class="mt-8 max-w-3xl space-y-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-7">
        <div>
            <h2 class="text-sm font-semibold text-slate-700 dark:text-white/90">Mesas e comandas (Food)</h2>
            <p class="mt-1 text-xs text-slate-400">Cadastro em faixa por unidade. O mapa operacional fica em Mesas e Comandas.</p>
        </div>

        <form method="GET" action="{{ route('empresa.edit') }}" class="flex flex-wrap items-end gap-3">
            <label class="text-sm font-medium text-slate-700 dark:text-white/80">Unidade
                <select name="unidade_id" class="form-control mt-1" onchange="this.form.submit()">
                    @foreach($unidades as $unidade)
                        <option value="{{ $unidade->id }}" @selected($unidade->id === $unidadeFoodId)>{{ $unidade->nome }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-medium text-slate-700 dark:text-white/80">Tipo
                <select name="tipo" class="form-control mt-1" onchange="this.form.submit()">
                    <option value="mesa" @selected($tipoFood === 'mesa')>Mesas</option>
                    <option value="comanda" @selected($tipoFood === 'comanda')>Comandas</option>
                </select>
            </label>
        </form>

        <div>
            <h3 class="text-sm font-semibold text-slate-700 dark:text-white/90">
                Cadastrar {{ $tipoFood === 'mesa' ? 'mesas' : 'comandas' }} em faixa
            </h3>
            <p class="mt-1 text-xs text-slate-400">
                Números únicos por tipo nesta unidade. Ex.: 1 a 20 cria as {{ $tipoFood }}s 1…20.
            </p>
            <form method="POST" action="{{ route('food.pontos.store') }}" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @csrf
                <input type="hidden" name="unidade_id" value="{{ $unidadeFoodId }}">
                <input type="hidden" name="tipo" value="{{ $tipoFood }}">

                <label class="text-sm font-medium text-slate-700 dark:text-white/80">
                    {{ $tipoFood === 'mesa' ? 'Mesa inicial' : 'Comanda inicial' }}
                    <input type="number" name="numero_inicial" min="1" max="9999" required value="{{ old('numero_inicial') }}"
                           class="form-control mt-1 @error('numero_inicial') border-rose-500 @enderror">
                    @error('numero_inicial')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </label>

                <label class="text-sm font-medium text-slate-700 dark:text-white/80">
                    {{ $tipoFood === 'mesa' ? 'Mesa final' : 'Comanda final' }}
                    <input type="number" name="numero_final" min="1" max="9999" required value="{{ old('numero_final') }}"
                           class="form-control mt-1 @error('numero_final') border-rose-500 @enderror">
                    @error('numero_final')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </label>

                <label class="text-sm font-medium text-slate-700 dark:text-white/80">
                    Capacidade (opcional)
                    <input type="number" name="capacidade" min="1" max="999" value="{{ old('capacidade') }}"
                           class="form-control mt-1 @error('capacidade') border-rose-500 @enderror" placeholder="Ex.: 4">
                    @error('capacidade')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </label>

                <div class="flex items-end">
                    <button type="submit" class="h-11 w-full rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">
                        Gerar faixa
                    </button>
                </div>
            </form>
        </div>

        <div>
            <h3 class="text-sm font-semibold text-slate-700 dark:text-white/90">
                {{ $tipoFood === 'mesa' ? 'Mesas' : 'Comandas' }} cadastradas
                <span class="font-normal text-slate-400">({{ $pontosFood->count() }})</span>
            </h3>
            @if($pontosFood->isEmpty())
                <p class="mt-2 text-sm text-slate-500">Nenhuma {{ $tipoFood }} nesta unidade.</p>
            @else
                <ul class="mt-3 max-h-56 divide-y overflow-y-auto rounded-xl border border-gray-200 dark:divide-gray-800 dark:border-gray-800">
                    @foreach($pontosFood as $ponto)
                        <li class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                            <span class="font-medium">{{ $ponto->identificacao }}</span>
                            <span class="capitalize text-slate-500">{{ $ponto->status }}</span>
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('food.index', ['unidade_id' => $unidadeFoodId, 'tipo' => $tipoFood]) }}" class="mt-3 inline-block text-sm font-semibold text-sky-600 hover:underline">
                    Abrir mapa operacional →
                </a>
            @endif
        </div>
    </section>
    @endif
    @endcan
@endsection

@push('scripts')
<script>
function consultaCnpjForm(cfg) {
    return {
        url: cfg.url,
        cnpj: cfg.cnpj || '',
        consultando: false,
        erro: '',
        msg: '',
        async consultar() {
            this.erro = '';
            this.msg = '';
            const digitos = String(this.cnpj || '').replace(/\D+/g, '');
            if (digitos.length !== 14) {
                this.erro = 'Informe um CNPJ com 14 dígitos.';
                return;
            }
            this.consultando = true;
            try {
                const res = await fetch(`${this.url}?cnpj=${encodeURIComponent(digitos)}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const data = await res.json();
                if (!res.ok) {
                    this.erro = data.message || 'Não foi possível consultar o CNPJ.';
                    return;
                }
                const set = (id, val) => {
                    const el = document.getElementById(id);
                    if (el && val) {
                        el.value = val;
                        el.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                };
                if (data.cnpj) this.cnpj = data.cnpj;
                set('empresa-nome', data.nome);
                set('empresa-razao-social', data.razao_social);
                set('empresa-ie', data.ie);
                set('empresa-email', data.email);
                set('empresa-telefone', data.telefone);
                set('empresa-endereco', data.endereco);
                set('empresa-ibge', data.codigo_municipio_ibge);
                set('empresa-cnae', data.cnae);
                this.msg = 'Dados preenchidos' + (data.fonte ? ` (${data.fonte})` : '') + '. Revise antes de salvar.';
            } catch (e) {
                this.erro = 'Falha de rede ao consultar o CNPJ.';
            } finally {
                this.consultando = false;
            }
        },
    };
}

function buscaCidadeIbge(cfg) {
    return {
        url: cfg.url,
        codigo: cfg.codigo || '',
        itens: [],
        aberta: false,
        async buscar() {
            const busca = String(this.codigo || '').trim();
            if (busca.length < 2) {
                this.itens = [];
                this.aberta = false;
                return;
            }
            try {
                const res = await fetch(`${this.url}?busca=${encodeURIComponent(busca)}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                this.itens = res.ok ? await res.json() : [];
                this.aberta = this.itens.length > 0;
            } catch (e) {
                this.itens = [];
                this.aberta = false;
            }
        },
        selecionar(item) {
            this.codigo = item.codigo;
            this.itens = [];
            this.aberta = false;
        },
    };
}
</script>
@endpush
