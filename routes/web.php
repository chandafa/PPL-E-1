<?php

use Illuminate\Support\Facades\Route;

Route::view('/docs', 'docs.swagger')->name('docs.swagger');
Route::get('/docs/openapi.yaml', function () {
    return response()->file(base_path('openapi.yaml'), [
        'Content-Type' => 'application/yaml; charset=utf-8',
        'Cache-Control' => 'no-store',
    ]);
})->name('docs.openapi');
