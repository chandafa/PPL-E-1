@extends('layouts.app')
@section('title', 'Catat e-Faktur')
@section('breadcrumb', 'Catat e-Faktur')
@section('content')
<div class="page-header"><div><h1 class="page-title" id="create-title">Catat e-Faktur Keluaran</h1><p class="page-description">Pilih invoice D-2, unggah PDF, lalu periksa hasil ekstraksi.</p></div><x-ui.button href="/tax-invoices" variant="secondary" id="create-back">Kembali</x-ui.button></div>
<div id="replacement-info" class="notice mb-5" hidden></div>
<form id="invoice-form" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)]">
    <div class="space-y-6"><x-ui.card title="1. Invoice proyek">
        @if(config('e1.d2.driver') === 'mock')
            <x-ui.select label="Invoice D-2" name="invoiceId" required disabled aria-describedby="invoiceId-help"><option value="">Memuat invoice D-2…</option></x-ui.select>
            <p id="invoiceId-help" class="field-help">Data dummy D-2 untuk simulasi. Hanya invoice tanpa faktur keluaran aktif yang ditampilkan. Cari nomor, ID invoice, proyek, atau klien.</p>
            <div id="invoice-context" class="mt-4" aria-live="polite"><p class="text-sm text-muted">Memuat daftar invoice…</p></div>
            <x-ui.button id="retry-invoices" variant="secondary" class="mt-3" hidden>Muat Ulang Invoice</x-ui.button>
        @else
            <x-ui.input label="ID invoice D-2" name="invoiceId" required maxlength="100" help="Masukkan ID invoice dari modul D-2." placeholder="Masukkan ID invoice" />
        @endif
        <div id="original-reference" class="mt-4" hidden></div>
    </x-ui.card><x-ui.card title="2. PDF e-Faktur"><x-ui.input label="Berkas e-Faktur" name="eFakturFile" type="file" accept="application/pdf,.pdf" required help="Unggah PDF e-Faktur yang dibuat di luar sistem. Maksimal sesuai batas unggah aplikasi." /><p id="file-limit" class="field-help"></p><x-ui.button id="extract" class="mt-5 w-full" variant="secondary" icon="scan">Periksa PDF</x-ui.button></x-ui.card></div>
    <div class="space-y-5"><x-ui.card title="3. Hasil ekstraksi"><div id="extraction-preview"><div class="empty-state px-0"><x-icon name="scan" class="mx-auto mb-3 h-8 w-8 text-muted" /><h3>PDF belum diperiksa</h3><p>Pilih invoice dan unggah PDF untuk melihat identitas, item, serta nilai pajak.</p></div></div></x-ui.card><div class="flex flex-wrap justify-end gap-3"><x-ui.button href="/tax-invoices" variant="secondary" id="cancel-create">Batal</x-ui.button><x-ui.button type="submit" id="save-invoice" icon="save" disabled>Simpan Faktur</x-ui.button></div><p class="text-xs leading-relaxed text-muted">Hasil ekstraksi disimpan sesuai PDF. Koreksi dilakukan melalui detail faktur setelah pencatatan.</p></div>
</form>
@endsection
