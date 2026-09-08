@props(['striped' => false])
<div {{ $attributes->class(['overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800']) }}>
    <table class="min-w-full divide-y divide-gray-200 text-left text-sm dark:divide-gray-800 [&_thead]:bg-gray-50 [&_thead]:text-xs [&_thead]:uppercase [&_thead]:tracking-wide [&_thead]:text-gray-500 [&_thead]:dark:bg-gray-800/50 [&_th]:px-5 [&_th]:py-3 [&_td]:px-5 [&_td]:py-3.5 [&_tbody]:divide-y [&_tbody]:divide-gray-100 [&_tbody]:dark:divide-gray-800 {{ $striped ? '[&_tbody_tr:nth-child(even)]:bg-gray-50/70 [&_tbody_tr:nth-child(even)]:dark:bg-white/[.02]' : '' }}">{{ $slot }}</table>
</div>
