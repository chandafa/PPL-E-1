@props(['variant' => 'info'])
<div {{ $attributes->class(['notice', 'notice-error' => $variant === 'error', 'notice-success' => $variant === 'success']) }} role="{{ $variant === 'error' ? 'alert' : 'status' }}">{{ $slot }}</div>
