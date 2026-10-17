@props(['name', 'label' => null])
<svg {{ $attributes->class(['icon']) }} @if($label) role="img" aria-label="{{ $label }}" @else aria-hidden="true" @endif><use href="#solar-{{ $name }}"></use></svg>
