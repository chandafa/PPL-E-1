@props(['variant' => 'primary', 'href' => null, 'icon' => null, 'type' => 'button'])
@php($style = ['primary' => 'btn-primary', 'secondary' => 'btn-secondary', 'danger' => 'btn-danger', 'ghost' => 'btn-ghost'][$variant] ?? 'btn-primary')
@if($href)
<a href="{{ $href }}" {{ $attributes->class(['btn', $style]) }}>@if($icon)<x-icon :name="$icon" />@endif{{ $slot }}</a>
@else
<button type="{{ $type }}" {{ $attributes->class(['btn', $style]) }}>@if($icon)<x-icon :name="$icon" />@endif{{ $slot }}</button>
@endif
