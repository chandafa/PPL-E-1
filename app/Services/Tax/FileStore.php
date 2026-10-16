<?php

namespace App\Services\Tax;

use App\Exceptions\ApiException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Validasi dan penyimpanan berkas PDF (e-Faktur, e-Billing, bukti pembayaran).
 * Tipe ditentukan dari isi (header %PDF-), bukan dari ekstensi atau Content-Type klien.
 */
class FileStore
{
    /**
     * @return string isi berkas
     *
     * @throws ApiException 400 dengan $errorCode bila berkas tidak ada, bukan PDF, atau terlalu besar
     */
    public function readPdf(mixed $file, string $errorCode, string $notPdfMessage): string
    {
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            throw ApiException::badRequest($errorCode, $notPdfMessage);
        }

        $maxKb = (int) config('e1.upload.max_kb', 5120);
        if ($file->getSize() > $maxKb * 1024) {
            throw ApiException::badRequest($errorCode, 'Ukuran berkas melebihi '.rtrim(rtrim(number_format($maxKb / 1024, 1), '0'), '.').' MB');
        }

        $binary = (string) file_get_contents($file->getRealPath());

        if (! str_contains(substr($binary, 0, 1024), '%PDF-')) {
            throw ApiException::badRequest($errorCode, $notPdfMessage);
        }

        return $binary;
    }

    /**
     * Simpan PDF dan kembalikan URI logis, mis. "/files/tax/e-faktur/<id>.pdf".
     */
    public function put(string $directory, string $id, string $binary): string
    {
        $path = "tax/{$directory}/{$id}.pdf";
        if (! Storage::disk((string) config('e1.upload.disk', 'local'))->put($path, $binary)) {
            throw new RuntimeException('Penyimpanan PDF gagal');
        }

        return '/files/'.$path;
    }

    public function delete(string $uri): void
    {
        $path = ltrim(substr($uri, strlen('/files/')), '/');
        Storage::disk((string) config('e1.upload.disk', 'local'))->delete($path);
    }
}
