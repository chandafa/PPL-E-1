@extends('layouts.app')
@section('title', 'Masuk')
@section('content')
@if(config('e1.f1.api_enabled'))
<x-ui.card title="Masuk dengan akun F-1">
    <form id="f1-login" method="post" class="space-y-4">
        <x-ui.input label="Email atau username" name="emailOrUsername" autocomplete="username" required maxlength="254" />
        <x-ui.input label="Kata sandi" name="password" type="password" autocomplete="current-password" required maxlength="1024" />
        <x-ui.button type="submit" class="w-full">Masuk</x-ui.button>
    </form>
</x-ui.card>
@endif
@if(config('e1.dev_login.enabled') && !app()->isProduction())
<div class="{{ config('e1.f1.api_enabled') ? 'mt-5' : '' }}">
<x-ui.card title="Akun Demo">
    @if(config('e1.dev_login.enabled') && !app()->isProduction())
    <form id="demo-login" method="post" class="space-y-4"><x-ui.input label="ID akun" name="akunId" value="AKN-0002" required maxlength="64" /><x-ui.select label="Peran" name="kodePeran"><option>Admin</option><option>CEO</option><option>COO</option></x-ui.select><x-ui.button type="submit" class="w-full">Masuk Akun Demo</x-ui.button></form>
    @else
    <p class="text-sm text-muted">Login akun demo tidak tersedia.</p>
    @endif
</x-ui.card>
</div>
@elseif(!config('e1.f1.api_enabled'))
<x-ui.card title="Masuk"><p class="text-sm text-muted">Layanan login belum dikonfigurasi. Hubungi pengelola aplikasi.</p></x-ui.card>
@endif
@endsection
