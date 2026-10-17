<?php

use App\Http\Controllers\Api\DemoInvoiceController;
use App\Http\Controllers\Api\DevLoginController;
use App\Http\Controllers\Api\V1\TaxInvoiceController;
use App\Http\Controllers\Api\V1\TaxPaymentController;
use App\Http\Controllers\Api\V1\TaxRecapController;
use App\Http\Controllers\Api\V1\TaxSummaryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API E-1: Pencatatan e-Faktur dan pajak proyek  (prefix otomatis /api)
|--------------------------------------------------------------------------
| ID operasi mengikuti openapi.yaml (API-KE01-01 s.d. API-KE01-11).
*/
// Login tiruan F-1 untuk Postman/demo lokal; mati kecuali E1_DEV_LOGIN=true (di luar kontrak openapi.yaml)
Route::post('dev/f1/login', DevLoginController::class);
Route::get('ui/d2/invoices', DemoInvoiceController::class)->middleware('f1.auth');
Route::get('ui/tax-recap-periods', [TaxRecapController::class, 'periods'])->middleware('f1.auth');

Route::prefix('v1')->group(function () {
    // Antarmuka web E-1 (internal): Admin, CEO, COO
    Route::middleware('f1.auth')->group(function () {
        Route::post('tax-invoices/extractions', [TaxInvoiceController::class, 'extract']);            // API-KE01-01
        Route::post('tax-invoices/outgoing', [TaxInvoiceController::class, 'storeOutgoing']);        // API-KE01-02
        Route::get('tax-invoices', [TaxInvoiceController::class, 'index']);                          // API-KE01-03
        Route::get('tax-invoices/{taxInvoiceId}', [TaxInvoiceController::class, 'show']);            // API-KE01-04
        Route::patch('tax-invoices/{taxInvoiceId}', [TaxInvoiceController::class, 'update']);        // API-KE01-05
        Route::get('tax-invoices/{taxInvoiceId}/history', [TaxInvoiceController::class, 'history']); // API-KE01-06

        Route::get('tax-recaps/{taxPeriod}', [TaxRecapController::class, 'show']);                   // API-KE01-07
        Route::get('tax-recaps/{taxPeriod}/export', [TaxRecapController::class, 'export']);          // API-KE01-09

        Route::post('tax-payments', [TaxPaymentController::class, 'store']);                         // API-KE01-08
        Route::patch('tax-payments/{taxPaymentId}', [TaxPaymentController::class, 'complete']);      // API-KE01-11
    });

    // Konsumen modul lain (E-2): Admin, CEO, COO + peran layanan yang dikonfigurasi
    Route::get('tax/summary', [TaxSummaryController::class, 'show'])                                 // API-KE01-10
        ->middleware('f1.auth:summary');
});
