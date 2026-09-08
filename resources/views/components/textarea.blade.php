@props(['label' => null, 'name', 'value' => null, 'help' => null, 'rows' => 4])
@php($id = $attributes->get('id', $name))
<div>@if($label)<label for="{{ $id }}" class="form-label">{{ $label }}</label>@endif<textarea name="{{ $name }}" id="{{ $id }}" rows="{{ $rows }}" {{ $attributes->except('id')->class(['form-control', 'border-rose-500' => $errors->has($name)]) }}>{{ old($name, $value) }}</textarea>@error($name)<p class="mt-1.5 text-xs text-rose-600">{{ $message }}</p>@else @if($help)<p class="mt-1.5 text-xs text-gray-500">{{ $help }}</p>@endif @enderror</div>
