@props(['status'])

@php
    $cores = [
        'ativo' => 'success', 'ativa' => 'success', 'aberto' => 'success', 'pago' => 'success',
        'aprovado' => 'success', 'enviado' => 'success', 'recebido' => 'success', 'pendente' => 'warning',
        'inativo' => 'neutral', 'fechado' => 'neutral', 'cancelado' => 'neutral', 'isento' => 'neutral',
        'bloqueado' => 'danger', 'bloqueada' => 'danger', 'atrasado' => 'danger',
        'falhou' => 'danger', 'recusado' => 'danger', 'expirada' => 'danger',
        'suspenso' => 'warning', 'em_processamento' => 'warning',
        'reservado' => 'warning', 'hospedado' => 'success', 'finalizado' => 'neutral', 'manutencao' => 'danger',
    ];
    $variant = $cores[$status] ?? 'neutral';
    $classes = [
        'success' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/15 dark:text-emerald-400',
        'warning' => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/15 dark:text-amber-400',
        'danger' => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-500/15 dark:text-rose-400',
        'neutral' => 'bg-gray-100 text-gray-600 ring-gray-500/10 dark:bg-gray-800 dark:text-gray-300',
    ];
@endphp

<span {{ $attributes->class("inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset {$classes[$variant]}") }}>
    <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
    {{ ucfirst(str_replace('_', ' ', $status)) }}
</span>
