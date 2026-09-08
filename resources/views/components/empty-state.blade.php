@props(['title' => 'Nenhum registro encontrado', 'description' => null])
<div {{ $attributes->class('flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 px-6 py-12 text-center dark:border-gray-700') }}>
    <svg class="mb-3 h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7 12 3 4 7m16 0-8 4-8-4m16 0v10l-8 4-8-4V7m8 4v10"/></svg>
    <p class="font-medium text-gray-700 dark:text-gray-200">{{ $title }}</p>
    @if($description)<p class="mt-1 max-w-md text-sm text-gray-500 dark:text-gray-400">{{ $description }}</p>@endif
    @isset($action)<div class="mt-5">{{ $action }}</div>@endisset
</div>
