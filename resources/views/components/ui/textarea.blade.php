@props(['label', 'name'])
<div><label class="field-label" for="{{ $name }}">{{ $label }}</label><textarea id="{{ $name }}" name="{{ $name }}" {{ $attributes->class(['field']) }}>{{ $slot }}</textarea></div>
