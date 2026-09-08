@props(['title' => null, 'subtitle' => null, 'padding' => true])
<section {{ $attributes->class(['overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-gray-900']) }}>
    @if ($title || $subtitle || isset($header) || isset($actions))
        <header class="flex flex-col gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
            <div>@if($title)<h2 class="font-semibold text-gray-900 dark:text-white">{{ $title }}</h2>@endif @if($subtitle)<p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $subtitle }}</p>@endif {{ $header ?? '' }}</div>
            @isset($actions)<div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>@endisset
        </header>
    @endif
    <div @class(['p-5 sm:p-6' => $padding])>{{ $slot }}</div>
    @isset($footer)<footer class="border-t border-gray-100 px-5 py-4 dark:border-gray-800">{{ $footer }}</footer>@endisset
</section>
