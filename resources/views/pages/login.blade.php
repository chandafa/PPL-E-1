@extends('layouts.app')
@section('title', 'Masuk')
@section('content')
<x-ui.card title="Login">
    <form id="f1-login" method="post" class="space-y-4">
        <x-ui.input label="Username" name="emailAtauUsername" placeholder="Masukkan username" required maxlength="255" autocomplete="username" />
        <x-ui.input label="Password" name="password" type="password" placeholder="Masukkan password" required maxlength="1024" autocomplete="current-password" />
        <x-ui.button type="submit" class="w-full">Masuk</x-ui.button>
    </form>
</x-ui.card>
@endsection
