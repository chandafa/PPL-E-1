<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/tax-invoices');
Route::view('/login', 'pages.login', ['page' => 'login'])->name('ui.login');
Route::view('/tax-invoices', 'pages.invoices', ['page' => 'invoices'])->name('ui.invoices');
Route::view('/d2-invoices', 'pages.d2-invoices', ['page' => 'd2-invoices'])->name('ui.d2-invoices');
Route::view('/tax-invoices/create', 'pages.create', ['page' => 'create'])->name('ui.invoices.create');
Route::get('/tax-invoices/{taxInvoiceId}', fn (string $taxInvoiceId) => view('pages.detail', [
    'page' => 'detail', 'taxInvoiceId' => $taxInvoiceId,
]))->name('ui.invoices.show');
Route::get('/tax-recaps', fn () => redirect()->route('ui.recap', [
    'taxPeriod' => now('Asia/Jakarta')->format('Y-m'),
]))->name('ui.recaps');
Route::get('/tax-recaps/{taxPeriod}', fn (string $taxPeriod) => view('pages.recap', [
    'page' => 'recap', 'taxPeriod' => $taxPeriod,
]))->where('taxPeriod', '[0-9]{4}-(0[1-9]|1[0-2])')->name('ui.recap');
Route::get('/tax-recaps/{taxPeriod}/payments/create', fn (string $taxPeriod) => view('pages.payment', [
    'page' => 'payment', 'taxPeriod' => $taxPeriod,
]))->where('taxPeriod', '[0-9]{4}-(0[1-9]|1[0-2])')->name('ui.payments');

Route::view('/docs', 'docs.swagger')->name('docs.swagger');
Route::get('/docs/openapi.yaml', function () {
    return response()->file(base_path('openapi.yaml'), [
        'Content-Type' => 'application/yaml; charset=utf-8',
        'Cache-Control' => 'no-store',
    ]);
})->name('docs.openapi');
