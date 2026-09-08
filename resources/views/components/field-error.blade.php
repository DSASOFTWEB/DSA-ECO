@props(['name'])
@error($name)
    <p {{ $attributes->class('mt-1.5 text-xs text-rose-600 dark:text-rose-400') }}>{{ $message }}</p>
@enderror
