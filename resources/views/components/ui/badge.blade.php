@props(['status' => 'NORMAL'])
@php($labels = ['NORMAL' => ['Normal', 'status-normal'], 'REPLACEMENT' => ['Pengganti', 'status-replacement'], 'CANCELLED' => ['Dibatalkan', 'status-cancelled'], 'INCOMPLETE' => ['Draft / Belum Konfirmasi', 'status-incomplete'], 'CONFIRMED' => ['Terkonfirmasi', 'status-confirmed'], 'NOT_RECORDED' => ['Belum dicatat', 'status-normal']])
<span {{ $attributes->class(['status', $labels[$status][1] ?? 'status-normal']) }}>{{ $labels[$status][0] ?? $status }}</span>
