@props(['variant' => 'primary', 'size' => 'md', 'href' => null, 'type' => 'button'])
@php
    $variants = ['primary'=>'bg-sky-600 text-white hover:bg-sky-700','secondary'=>'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800','danger'=>'bg-rose-600 text-white hover:bg-rose-700','success'=>'bg-emerald-600 text-white hover:bg-emerald-700','ghost'=>'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'];
    $sizes = ['sm'=>'px-3 py-2 text-xs','md'=>'px-4 py-2.5 text-sm','lg'=>'px-5 py-3 text-base'];
    $classes = 'inline-flex items-center justify-center gap-2 rounded-lg font-medium shadow-sm transition disabled:pointer-events-none disabled:opacity-50 '.($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']);
@endphp
@if($href)<a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>@else<button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>@endif
