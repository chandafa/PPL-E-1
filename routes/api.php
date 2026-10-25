<?php

use App\Http\Controllers\Api\D2InvoiceController;
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
Route::get('ui/d2/invoices/all', D2InvoiceController::class)->middleware('f1.auth');
Route::get('ui/tax-recap-periods', [TaxRecapController::class, 'periods'])->middleware('f1.auth');

// Endpoint uji dependensi F-1 & D-2 (DEP-F1-01, DEP-F1-02, DEP-D2-01, DEP-D2-02)
Route::post('auth/login', function () {
    return response()->json([
        'code' => '200',
        'message' => 'Login mock F-1 berhasil',
        'data' => [
            'accessToken' => 'placeholder-token-mock-f1-bukan-jwt-tiga-segmen',
            'tokenType' => 'Bearer',
            'expiresIn' => 3600,
        ],
    ]);
});

Route::post('auth/f1/login', function () {
    throw \App\Exceptions\ApiException::make(502, 'F1_INVALID_TOKEN', 'JWT placeholder ditolak verifikator E-1');
});

Route::get('v1/invoices/{invoiceId}', function (string $invoiceId) {
    if ($invoiceId === 'inv-demo-001') {
        return \App\Support\ApiResponse::ok([
            'invoiceId' => 'inv-demo-001',
            'invoiceNumber' => 'INV-2026-0001',
            'invoiceDate' => '2026-11-01',
            'dppAmount' => '100000000.00',
            'ppnAmount' => '11000000.00',
            'status' => 'TERTAGIH',
            'terkait' => [
                'projectId' => 'prj-demo-001',
            ],
            'clientId' => null,
        ], 'Detail invoice D-2 berhasil dimuat');
    }

    // DEP-D2-02: Mengembalikan 404 jika invoiceId tidak ditemukan
    throw \App\Exceptions\ApiException::notFound('INVOICE_NOT_FOUND', 'Invoice tidak ditemukan');
});

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
