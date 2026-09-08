@props(['label' => null, 'name', 'help' => null])
@php($id = $attributes->get('id', $name))
<div>@if($label)<label for="{{ $id }}" class="form-label">{{ $label }}</label>@endif<select name="{{ $name }}" id="{{ $id }}" {{ $attributes->except('id')->class(['form-control', 'border-rose-500 focus:border-rose-500 focus:ring-rose-500' => $errors->has($name)]) }}>{{ $slot }}</select>@error($name)<p class="mt-1.5 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@else @if($help)<p class="mt-1.5 text-xs text-gray-500">{{ $help }}</p>@endif @enderror</div>
