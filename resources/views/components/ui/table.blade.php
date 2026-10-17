@props(['label'])
<div class="data-scroll" role="region" aria-label="{{ $label }}" tabindex="0"><table {{ $attributes->class(['data-table']) }}>{{ $slot }}</table></div>
