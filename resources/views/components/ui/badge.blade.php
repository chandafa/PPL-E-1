@props(['status' => 'NORMAL'])
@php($labels = ['NORMAL' => ['Normal', 'status-normal'], 'REPLACEMENT' => ['Pengganti', 'status-replacement'], 'CANCELLED' => ['Dibatalkan', 'status-cancelled'], 'INCOMPLETE' => ['Incompleted', 'status-incomplete'], 'CONFIRMED' => ['Confirmed', 'status-confirmed'], 'NOT_RECORDED' => ['Belum dicatat', 'status-normal']])
<span {{ $attributes->class(['status', $labels[$status][1] ?? 'status-normal']) }}>{{ $labels[$status][0] ?? $status }}</span>
