<?php

// Jalankan: php scripts/generate-api-reference.php (dependensi Composer dev diperlukan).
require __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

$root = dirname(__DIR__);
$spec = Yaml::parseFile($root.'/openapi.yaml');
$resolve = function (array $value) use ($spec): array {
    if (! isset($value['$ref'])) {
        return $value;
    }
    $node = $spec;
    foreach (explode('/', substr($value['$ref'], 2)) as $key) {
        $node = $node[$key];
    }

    return $node;
};
$out = "# Respons berhasil dan gagal setiap API\n\n";
$out .= "[Indeks dokumentasi](../README.md) · [README proyek](../../README.md) · [Cara menguji](POSTMAN.md) · [OpenAPI](../../openapi.yaml)\n\n";
$out .= "Dihasilkan dari OpenAPI **{$spec['info']['version']}** dengan `php scripts/generate-api-reference.php`. Sumber field, tipe, enum, dan contoh tersedia pada kontrak yang sama di Swagger `/docs`.\n\n";
$out .= "Keputusan demo lokal dan penyelarasan terhadap PDF spesifikasi/kontrak dijelaskan pada [Adendum E-1 v0.3](../acuan/ADENDUM_DEMO_E1_v0.3.md) ([PDF](../acuan/ADENDUM_DEMO_E1_v0.3.pdf)). NTPN dan bukti pembayaran boleh menyusul saat pencatatan Draft (API-KE01-08), tetapi wajib saat konfirmasi (API-KE01-11).\n\n";
$out .= "## Ketentuan umum\n\n- Seluruh sukses JSON memakai HTTP 200 dan amplop `{code,message,data}`; `code` string. API-KE01-09 mengirim berkas biner.\n- Gagal memakai `{code,message,data:null,errorCode}`; `code` sama dengan status HTTP.\n- Nominal JSON string decimal tanpa pemisah ribuan, misalnya `11000000.00`. Nilai uang tidak dikirim sebagai float.\n- Tanggal `YYYY-MM-DD`, masa pajak `YYYY-MM`, waktu ISO 8601 UTC. PDF maksimum 5 MiB per berkas.\n- ID UUID pada contoh adalah fiktif; gunakan ID dari hasil penyimpanan saat menguji.\n- Token F-1 wajib: tanpa token/tidak sah = 401, peran ditolak = 403.\n- `type` dan `vendorName` merupakan field tambahan yang sudah didokumentasikan. API pembuatan hanya menghasilkan `OUTGOING`. URI `/files/tax/...` adalah referensi penyimpanan privat, belum endpoint unduh publik.\n- Rancangan UI v0.2: NORMAL dan REPLACEMENT keluaran aktif dihitung. CANCELLED dan asal terganti tetap terlihat dengan `isCounted:false`.\n- `status` pembayaran adalah satu catatan; `paymentStatus` adalah seluruh masa pajak. Satu Draft saja membuat rekap INCOMPLETE. Filter proyek tidak mengubah lingkup status pembayaran.\n- `paymentDifference` = jumlah setor seluruh catatan periode, termasuk Draft, dikurangi total PPN. Kompensasi otomatis belum dibuat (Q-24).\n\n";
$out .= "Kesalahan umum di seluruh API: 404 `NOT_FOUND` (rute), 405 `METHOD_NOT_ALLOWED`, 413 `PAYLOAD_TOO_LARGE`, 500 `INTERNAL_ERROR` (termasuk gagal menyimpan PDF). Handler juga menggunakan 400 `VALIDATION_ERROR`, 429 `TOO_MANY_REQUESTS`, atau `HTTP_ERROR` untuk kesalahan framework yang relevan. Respons gagal tidak memuat rincian exception atau kredensial.\n\n";
foreach ($spec['paths'] as $path => $methods) {
    foreach ($methods as $method => $op) {
        if (! isset($op['operationId'])) {
            continue;
        }
        $out .= "## {$op['summary']}\n\n`".strtoupper($method)." {$path}`\n\n{$op['description']}\n\n";
        foreach ($op['responses'] as $status => $response) {
            $response = $resolve($response);
            $out .= "### HTTP {$status}\n\n{$response['description']}\n\n";
            $json = $response['content']['application/json'] ?? null;
            if ($json !== null) {
                foreach (($json['examples'] ?? []) as $name => $example) {
                    $out .= '**'.($example['value']['errorCode'] ?? $name).'**';
                    if (isset($example['summary'])) {
                        $out .= ': '.$example['summary'];
                    }
                    $out .= "\n\n```json\n".json_encode($example['value'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n```\n\n";
                }
            } else {
                $out .= "Badan respons berisi berkas, bukan JSON. `Content-Disposition: attachment; filename=\"rekap-pajak-{taxPeriod}.xlsx\"` atau `.pdf`.\n\n";
                foreach (array_keys($response['content'] ?? []) as $mime) {
                    $out .= '- `Content-Type: '.$mime."`\n";
                }
                $out .= "\nExcel memuat satu lembar Rekap Pajak dengan Ringkasan, Daftar Faktur, dan Pembayaran berurutan seperti PDF. Nominal dan ID ditulis sebagai teks untuk menjaga seluruh digit; PDF menampilkan rupiah dengan format Indonesia.\n\n";
            }
        }
    }
}
file_put_contents($root.'/docs/api/RESPON_API.md', $out);
if (isset($argv[1])) {
    file_put_contents($argv[1], json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}
echo "docs/api/RESPON_API.md diperbarui dari 11 operasi OpenAPI.\n";
