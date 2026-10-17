@extends('layouts.app')
@section('title', 'Masuk')
@section('content')
<x-ui.card title="Akun Demo">
    @if(config('e1.dev_login.enabled') && !app()->isProduction())
    <form id="demo-login" method="post" class="space-y-4"><x-ui.input label="ID akun" name="akunId" value="AKN-0002" required maxlength="64" /><x-ui.select label="Peran" name="kodePeran"><option>Admin</option><option>CEO</option><option>COO</option></x-ui.select><x-ui.button type="submit" class="w-full">Masuk Akun Demo</x-ui.button></form>
    @else
    <p class="text-sm text-muted">Login akun demo tidak tersedia.</p>
    @endif
</x-ui.card>
@endsection
