<?php

$csv = fn (string $value): array => array_values(array_filter(array_map('trim', explode(',', $value)), 'strlen'));

return [

    /*
    |--------------------------------------------------------------------------
    | Hak akses (BL-09, Q-19)
    |--------------------------------------------------------------------------
    | Admin, CEO, dan COO memakai hak sama untuk semua operasi E-1 sementara
    | matriks hak per aksi menunggu keputusan mitra (Q-19). Peran lain 403.
    | "summary_extra" memberi peran/akun layanan tambahan hak baca API-KE01-10
    | (konsumen E-2) tanpa membuka operasi lain.
    */
    'roles' => [
        'allowed' => $csv((string) env('E1_ALLOWED_ROLES', 'Admin,CEO,COO')),
        'summary_extra' => $csv((string) env('E1_SUMMARY_EXTRA_ROLES', '')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Token F-1 (JWT Bearer)
    |--------------------------------------------------------------------------
    | Slide 2: layanan memeriksa tanda tangan, iss, aud, dan exp. Pemilik kunci,
    | masa token, dan nama klaim ditetapkan F-1 (OPEN-08); nilai di sini dapat
    | dikonfigurasi lewat environment.
    */
    'f1' => [
        'api_enabled' => (bool) env('F1_API_ENABLED', false),
        'base_url' => env('F1_BASE_URL', 'http://127.0.0.1:4100'),
        'login_path' => env('F1_LOGIN_PATH', '/auth/login'),
        'role_path' => env('F1_ROLE_PATH', '/accounts/{accountId}/role'),
        'profile_path' => env('F1_PROFILE_PATH', '/identity/accounts/{accountId}'),
        'timeout' => (int) env('F1_TIMEOUT', 5),
        'check_account' => (bool) env('F1_CHECK_ACCOUNT', false),
        'algo' => env('F1_JWT_ALGO', 'HS256'),
        'secret' => env('F1_JWT_SECRET'),
        'public_key_path' => env('F1_JWT_PUBLIC_KEY_PATH'),
        'issuer' => env('F1_JWT_ISSUER'),
        'audience' => env('F1_JWT_AUDIENCE'),
        'leeway' => (int) env('F1_JWT_LEEWAY', 30),
        'claim_akun_id' => env('F1_CLAIM_AKUN_ID', 'accountId'),
        'claim_role' => env('F1_CLAIM_ROLE', 'roleCode'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Login tiruan F-1 (Postman/demo lokal)
    |--------------------------------------------------------------------------
    | POST /api/dev/f1/login menerbitkan token palsu. Default MATI; nyalakan hanya
    | di lokal (E1_DEV_LOGIN=true). Selalu nonaktif di production.
    */
    'dev_login' => [
        'enabled' => (bool) env('E1_DEV_LOGIN', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Integrasi D-2 (invoice)
    |--------------------------------------------------------------------------
    | driver "mock" membaca snapshot tabel D-2 yang ditanam oleh ModulD2Seeder;
    | driver "http" memanggil layanan D-2 sebenarnya.
    */
    'd2' => [
        'driver' => env('D2_DRIVER', 'mock'),
        'base_url' => env('D2_BASE_URL'),
        'invoice_path' => env('D2_INVOICE_PATH', '/api/v1/invoices/{invoiceId}'),
        'timeout' => (int) env('D2_TIMEOUT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Berkas PDF
    |--------------------------------------------------------------------------
    */
    'upload' => [
        'max_kb' => (int) env('E1_UPLOAD_MAX_KB', 5120),
        'disk' => env('E1_STORAGE_DISK', 'local'),
    ],

    'pagination' => [
        'default' => 20,
        'max' => 100,
    ],
];
