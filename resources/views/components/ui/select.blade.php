@props(['label', 'name'])
<div><label class="field-label" for="{{ $name }}">{{ $label }}</label><select id="{{ $name }}" name="{{ $name }}" {{ $attributes->class(['field']) }}>{{ $slot }}</select></div>
