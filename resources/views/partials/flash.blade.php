@php
    $alerts = array_filter([
        ['message' => session('sucesso') ?? session('success'), 'type' => 'success', 'title' => 'Tudo certo!'],
        ['message' => session('erro') ?? session('error'), 'type' => 'danger', 'title' => 'Não foi possível concluir'],
        ['message' => session('aviso') ?? session('warning'), 'type' => 'warning', 'title' => 'Atenção'],
        ['message' => session('info'), 'type' => 'info', 'title' => 'Informação'],
    ], fn ($alert) => filled($alert['message']));
@endphp
<div class="mb-6 space-y-3" aria-live="polite">
    @foreach ($alerts as $alert)
        <x-alert :type="$alert['type']" :title="$alert['title']" dismissible>{{ $alert['message'] }}</x-alert>
    @endforeach
    @if (isset($errors) && $errors->any())
        <x-alert type="danger" title="Corrija os campos abaixo:" dismissible><ul class="mt-1 list-inside list-disc space-y-0.5">@foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul></x-alert>
    @endif
</div>
