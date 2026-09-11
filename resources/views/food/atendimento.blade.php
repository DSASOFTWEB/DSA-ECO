@extends('layouts.app')

@section('titulo', $atendimento->ponto->identificacao)

@section('conteudo')
<div class="grid gap-6 xl:grid-cols-3">
    <div class="space-y-6 xl:col-span-2">
        <x-card :title="$atendimento->ponto->identificacao" :subtitle="'Aberto por '.$atendimento->abertoPor->name.' em '.$atendimento->aberto_em->format('d/m/Y H:i')">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="border-b text-left text-xs uppercase text-gray-500"><th class="py-3">Item</th><th>Qtd.</th><th>Unitário</th><th>Total</th><th></th></tr></thead>
                    <tbody class="divide-y dark:divide-gray-800">
                    @forelse($atendimento->itens as $item)
                        <tr @class(['opacity-45 line-through' => $item->status === 'cancelado'])>
                            <td class="py-3"><strong>{{ $item->produto->nome }}</strong>@if($item->observacao)<div class="text-xs text-gray-500">{{ $item->observacao }}</div>@endif</td>
                            <td>{{ $item->quantidade }}</td><td>R$ {{ number_format((float)$item->preco_unitario,2,',','.') }}</td><td>R$ {{ number_format((float)$item->subtotal,2,',','.') }}</td>
                            <td class="text-right">@if($item->status === 'ativo')<form method="POST" action="{{ route('food.atendimentos.itens.cancelar', [$atendimento, $item]) }}" class="inline">@csrf @method('DELETE')<input type="hidden" name="motivo" value="Cancelado pelo operador"><button class="text-xs font-semibold text-rose-600" onclick="return confirm('Cancelar este item?')">Cancelar</button></form>@endif</td>
                        </tr>
                    @empty<tr><td colspan="5" class="py-8 text-center text-gray-500">Nenhum item lançado.</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        @if($atendimento->estaEmAndamento())
        <x-card title="Lançar produto">
            <form method="POST" action="{{ route('food.atendimentos.itens.store', $atendimento) }}" class="grid gap-4 sm:grid-cols-6">@csrf
                <label class="text-sm sm:col-span-3">Produto<select name="produto_id" required class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"><option value="">Selecione</option>@foreach($produtos as $produto)<option value="{{ $produto->id }}">{{ $produto->nome }} — R$ {{ number_format((float)$produto->preco_venda,2,',','.') }}</option>@endforeach</select></label>
                <label class="text-sm">Quantidade<input type="number" name="quantidade" value="1" min="1" required class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"></label>
                <label class="text-sm">Desconto<input type="number" name="desconto" value="0" min="0" step="0.01" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"></label>
                <x-button type="submit" class="self-end">Adicionar</x-button>
                <label class="text-sm sm:col-span-6">Observação do preparo<input name="observacao" maxlength="500" placeholder="Ex.: sem cebola" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"></label>
            </form>
        </x-card>
        @endif
    </div>

    <div class="space-y-6">
        <x-card title="Resumo da conta">
            <dl class="space-y-3 text-sm"><div class="flex justify-between"><dt>Subtotal</dt><dd>R$ {{ number_format((float)$atendimento->subtotal,2,',','.') }}</dd></div><div class="flex justify-between text-lg font-bold"><dt>Total atual</dt><dd>R$ {{ number_format((float)$atendimento->valor_total,2,',','.') }}</dd></div></dl>
            <div class="mt-5 grid gap-2">
                <x-button :href="route('food.atendimentos.conta', $atendimento)" variant="secondary">Imprimir conta</x-button>
                @if($atendimento->estaEmAndamento())<form method="POST" action="{{ route('food.atendimentos.pre-fechar', $atendimento) }}">@csrf<x-button type="submit" variant="secondary" class="w-full">Pré-fechar</x-button></form>@endif
            </div>
        </x-card>

        @if($atendimento->estaEmAndamento())
        <x-card title="Transferir ou juntar">
            <form method="POST" action="{{ route('food.atendimentos.transferir', $atendimento) }}" class="space-y-3">@csrf
                <select name="ponto_destino_id" required class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"><option value="">Selecione o destino</option>@foreach($destinos as $destino)<option value="{{ $destino->id }}">{{ $destino->identificacao }} — {{ $destino->status }}</option>@endforeach</select>
                <p class="text-xs text-gray-500">Destino livre: transfere a conta. Destino ocupado: junta os itens na conta existente.</p>
                <x-button type="submit" variant="secondary" class="w-full" onclick="return confirm('Confirmar transferência?')">Transferir</x-button>
            </form>
        </x-card>

        <x-card title="Fechar conta">
            <form method="POST" action="{{ route('food.atendimentos.fechar', $atendimento) }}" class="space-y-3" x-data="{ subtotal: {{ (float)$atendimento->subtotal }}, desconto: 0, taxa: 10, get total(){ return Math.max(0, this.subtotal + this.subtotal*this.taxa/100 - this.desconto).toFixed(2) } }">@csrf
                <label class="block text-sm">Caixa<select name="caixa_id" required class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"><option value="">Selecione</option>@foreach($caixas as $caixa)<option value="{{ $caixa->id }}">Caixa #{{ $caixa->id }} {{ $caixa->terminal?->nome }}</option>@endforeach</select></label>
                <div class="grid grid-cols-2 gap-3"><label class="text-sm">Desconto<input name="desconto" type="number" min="0" step="0.01" x-model.number="desconto" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"></label><label class="text-sm">Serviço %<input name="percentual_servico" type="number" min="0" max="100" step="0.01" x-model.number="taxa" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"></label></div>
                <label class="block text-sm">Forma<select name="pagamentos[0][forma]" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"><option value="dinheiro">Dinheiro</option><option value="pix">Pix</option><option value="credito">Crédito</option><option value="debito">Débito</option><option value="fiado">Fiado</option><option value="outro">Outro</option></select></label>
                <label class="block text-sm">Valor<input name="pagamentos[0][valor]" type="number" min="0.01" step="0.01" required :value="total" class="mt-1 w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"></label>
                <div class="rounded-lg bg-gray-100 p-3 text-right text-lg font-bold dark:bg-gray-800">Total: R$ <span x-text="total.replace('.', ',')"></span></div>
                <x-button type="submit" variant="success" class="w-full" :disabled="$caixas->isEmpty()">Confirmar fechamento</x-button>
                @if($caixas->isEmpty())<p class="text-xs text-rose-600">Abra um caixa nesta unidade antes de fechar.</p>@endif
            </form>
        </x-card>
        @endif
    </div>
</div>
@endsection
