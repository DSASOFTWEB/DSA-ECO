{{--
    Formulário "Enviar pelo WhatsApp" da carteirinha. Número já preenchido
    com o WhatsApp (ou telefone) do titular; no titular com dependentes,
    opção de mandar a família inteira num PDF só.
    Parâmetros: $carteirinha, $whatsappIntegrado (Evolution API configurada).
--}}
@php
    $clienteBase = $carteirinha->cliente ?? $carteirinha->dependente?->cliente;
    $numeroPadrao = $clienteBase?->whatsapp ?: $clienteBase?->telefone;
    $tamanhoFamilia = $clienteBase
        ? collect([$clienteBase->carteirinha])->merge($clienteBase->dependentes->pluck('carteirinha'))->filter()->unique('id')->count()
        : 1;
@endphp
<form method="POST" action="{{ route('carteirinhas.whatsapp', $carteirinha) }}"
      class="flex flex-wrap items-center justify-end gap-2 text-left"
      onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'Enviando...'">
    @csrf
    <input type="tel" name="telefone" required value="{{ $numeroPadrao }}" placeholder="(83) 99999-9999"
           class="h-9 w-44 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-emerald-500 dark:border-gray-700 dark:text-white/90">
    @if ($tamanhoFamilia > 1)
        <label class="flex items-center gap-1.5 text-xs font-medium text-gray-600 dark:text-gray-300">
            <input type="checkbox" name="familia" value="1" @checked($carteirinha->cliente_id) class="rounded border-gray-300">
            Família inteira ({{ $tamanhoFamilia }})
        </label>
    @endif
    <button @disabled(! $whatsappIntegrado) class="h-9 rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">Enviar</button>
    @if ($whatsappIntegrado)
        <p class="w-full text-right text-[11px] font-normal text-gray-400">Vai como arquivo PDF pelo WhatsApp da empresa.</p>
    @else
        <p class="w-full text-right text-[11px] font-semibold text-amber-600 dark:text-amber-400">
            WhatsApp da empresa não configurado: configure a Evolution API em
            @can('empresa.gerenciar')<a href="{{ route('empresa.edit', ['aba' => 'gerencial']) }}" class="underline">Minha Empresa → Gerencial</a>@else Minha Empresa → Gerencial @endcan.
        </p>
    @endif
</form>
