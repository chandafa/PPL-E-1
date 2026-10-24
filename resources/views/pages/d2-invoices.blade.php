@extends('layouts.app')
@section('title', 'Invoice D-2')
@section('content')
<div class="page-header"><div><h1 class="page-title">Invoice D-2</h1><p class="page-description">Daftar invoice dari modul D-2 sebagai rujukan pencatatan e-Faktur keluar. Nilai komersial berasal langsung dari D-2.</p></div></div>

<div id="d2-invoices-alert" class="notice mb-5" role="alert" tabindex="-1" hidden></div>

<section class="panel">
    <div class="panel-heading"><h2 class="panel-title">Daftar Invoice D-2</h2><span id="d2-invoice-count" class="text-xs text-muted"></span></div>
    <div id="d2-invoice-list"><x-ui.skeleton /></div>
</section>

<p class="mt-4 text-xs leading-relaxed text-muted">Invoice di atas bersifat referensi dari modul D-2. E-1 tidak mengubah data invoice di D-2. Pilih invoice saat mencatat e-Faktur keluaran melalui halaman <a href="/tax-invoices/create" class="text-link">Catat e-Faktur Keluaran</a>.</p>
@endsection
