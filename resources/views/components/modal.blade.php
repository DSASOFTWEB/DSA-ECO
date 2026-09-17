@props(['name', 'title' => null, 'maxWidth' => '2xl'])
@php($widths=['sm'=>'max-w-sm','md'=>'max-w-md','lg'=>'max-w-lg','xl'=>'max-w-xl','2xl'=>'max-w-2xl','3xl'=>'max-w-3xl','4xl'=>'max-w-4xl','5xl'=>'max-w-5xl','6xl'=>'max-w-6xl'])
<div x-data="{ open: false }" x-on:open-modal.window="if ($event.detail === '{{ $name }}') open = true" x-on:close-modal.window="if (!$event.detail || $event.detail === '{{ $name }}') open = false" x-on:keydown.escape.window="open = false" x-show="open" x-cloak class="fixed inset-0 z-[60] overflow-y-auto" role="dialog" aria-modal="true">
    <div class="flex min-h-full items-center justify-center p-4"><div x-show="open" x-transition.opacity class="fixed inset-0 bg-gray-950/60 backdrop-blur-sm" @click="open=false"></div><div x-show="open" x-transition class="relative w-full {{ $widths[$maxWidth] ?? $widths['2xl'] }} rounded-2xl bg-white shadow-xl dark:bg-gray-900">
        @if($title)<div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-800"><h2 class="text-lg font-semibold">{{ $title }}</h2><button type="button" class="icon-button" @click="open=false" aria-label="Fechar">&times;</button></div>@endif
        <div class="p-6">{{ $slot }}</div>@isset($footer)<div class="flex justify-end gap-3 border-t border-gray-200 px-6 py-4 dark:border-gray-800">{{ $footer }}</div>@endisset
    </div></div>
</div>
