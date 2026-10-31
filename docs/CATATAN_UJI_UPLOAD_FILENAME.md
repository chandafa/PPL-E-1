> Pembaruan 31 Oktober 2026: AUD-01 (rantai pengganti), AUD-02 (refresh rekap), AUD-03 (batas agregat), dan AUD-04 (struktur PDF) telah diperbaiki di E-1. Lihat [hasil integrasi dan regresi](integrasi/README.md). Keunikan NTPN telah ditolak 409. OBS-02 tentang koreksi setelah pembayaran tetap memerlukan keputusan kebijakan. Isi berikut dipertahankan sebagai bukti historis, bukan status kerentanan terbaru.

Engagement: Pentest/WAPT pada proyek PPL-E-1.

# Uji perubahan ekstensi upload

Tanggal: 25 Oktober 2026. Metodologi: `bb-methodology`, checklist
`hunt-file-upload`, dan validasi kesimpulan dengan `triage-validation`.

Tujuan: membuktikan pengaruh metadata multipart terhadap validasi konten,
penyimpanan, dan integritas dokumen pembayaran. Kelas yang diuji: file upload
dengan manipulasi nama/MIME dan pemalsuan penanda PDF. Lingkup: aplikasi lokal
dan database pengujian, tanpa pengujian host eksternal.

## Boundary dan filename

Ekstensi diubah pada atribut `filename` dalam header bagian multipart:

```http
Content-Type: multipart/form-data; boundary=q8v2m6z9r4

--q8v2m6z9r4
Content-Disposition: form-data; name="invoiceId"

inv-demo-001
--q8v2m6z9r4
Content-Disposition: form-data; name="eFakturFile"; filename="dokumen.php"
Content-Type: application/pdf

<byte PDF asli>
--q8v2m6z9r4--
```

Boundary merupakan pemisah, bukan atribut ekstensi. Saat menggunakan Burp
Repeater, ubah hanya filename terlebih dahulu; pertahankan boundary dan byte
file agar penyebab perbedaan dapat diisolasi. Setelah itu variasikan MIME,
lalu konten. Biarkan alat menghitung ulang Content-Length.

## Hasil yang direproduksi

Pengujian memakai request melalui kernel HTTP Laravel, token uji Admin,
database `e1_pajak_test`, dan `Storage::fake('local')`.

| Skenario | Hasil |
| --- | --- |
| PDF e-Faktur asli, nama `.pdf`, MIME PDF | 200; tersimpan sebagai UUID `.pdf` |
| PDF e-Faktur asli, nama `.php`, MIME PHP | 200; tersimpan sebagai UUID `.pdf` |
| PDF e-Faktur asli, nama `.pdf.php`, MIME PDF | 200; tersimpan sebagai UUID `.pdf` |
| PDF e-Faktur asli, nama `.pHp`, MIME octet-stream | 200; tersimpan sebagai UUID `.pdf` |
| Konten PHP inert tanpa `%PDF-`, nama `.php`, MIME PDF | 400 `INVALID_FILE`; tidak tersimpan |
| Konten palsu berpenanda `%PDF-`, nama `.php`, alur faktur | 400 `EXTRACTION_FAILED`; tidak tersimpan |
| Konten palsu yang sama, e-Billing dan bukti saat POST pembayaran | 200 `CONFIRMED`; byte palsu tersimpan sebagai `.pdf` |
| Konten palsu yang sama, bukti saat PATCH pelengkapan pembayaran | 200 `CONFIRMED`; byte palsu tersimpan sebagai `.pdf` |

Fixture konten palsu berikut tidak menjalankan fungsi atau perintah apa pun:

```php
<?php /* q8v2m6z9r4 %PDF-1.7 */ ?>
```

Assertion memeriksa isi file yang tersimpan, URI, status pembayaran, penolakan
terstruktur, dan ketiadaan file/baris faktur pada kasus gagal. Kesimpulan tidak
hanya berdasarkan HTTP 200.

## Penjelasan dan batas dampak

`app/Services/Tax/FileStore.php:34` hanya mencari `%PDF-` dalam 1.024 byte
pertama. Nama dan MIME klien tidak dipakai. Karena itu perubahan ekstensi
PDF asli diterima sesuai komentar desain pada kelas ini; hal tersebut sendiri
bukan bukti upload executable.

`FileStore::put()` membentuk `tax/<direktori>/<id>.pdf`; pemanggil menggunakan
ID buatan server. Konfigurasi default disk lokal mengarah ke
`storage/app/private`. Tidak ada bukti eksekusi PHP dari pengujian ini.
Konfigurasi web server deployment dan pengambilan file melalui jaringan belum
diuji, sehingga tidak ada klaim RCE atau jaminan keamanan deployment.

Perbedaan penting: faktur melewati `EFakturPdfParser`, sedangkan e-Billing dan
bukti pembayaran hanya melewati pemeriksaan penanda tersebut. Bukti bukan PDF
dapat memenuhi syarat keberadaan dokumen dan menghasilkan status `CONFIRMED`
ketika field lain valid. Dampak yang terbukti terbatas pada integritas format
dokumen pendukung; keaslian transaksi bank, kerugian finansial, akses lintas
pengguna, dan peningkatan hak akses tidak dibuktikan.

Hasil validasi: catat kelemahan pemeriksaan format sebagai observasi WAPT.
Prasyaratnya peran yang memang berwenang mengunggah dokumen. Tidak dinilai
High/Critical dan tidak diperlakukan sebagai temuan RCE atau auth bypass.
Ini belum merupakan laporan bug bounty dengan pembuktian dampak lintas pengguna.

## Reproduksi otomatis

```bash
php artisan test --compact tests/Feature/UploadFilenameTamperingTest.php
vendor/bin/pint --test tests/Feature/UploadFilenameTamperingTest.php
```

Hasil: 8 tests passed, 40 assertions; pemeriksaan format lulus.
Test merupakan karakterisasi perilaku saat ini: assertion penerimaan file
palsu mendokumentasikan kelemahan, bukan persyaratan mempertahankannya.

## Reproduksi multipart melalui jaringan untuk retest manual

Siapkan aplikasi lokal, token sesi sah pada variabel `TOKEN`, invoice demo yang
belum memiliki faktur aktif, dan PDF e-Faktur valid pada `dokumen.pdf`.
Endpoint ekstraksi tidak menyimpan faktur. Bandingkan body kedua respons:

```bash
curl --silent --show-error http://127.0.0.1:8080/api/v1/tax-invoices/extractions \
  -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' \
  -F 'invoiceId=inv-demo-001' \
  -F 'eFakturFile=@dokumen.pdf;filename=dokumen.pdf;type=application/pdf'

curl --silent --show-error http://127.0.0.1:8080/api/v1/tax-invoices/extractions \
  -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' \
  -F 'invoiceId=inv-demo-001' \
  -F 'eFakturFile=@dokumen.pdf;filename=dokumen.php;type=application/pdf'
```

Perintah ini disediakan untuk retest manual dan belum dijalankan dalam sesi
ini. Feature test melewati parser multipart native PHP karena langsung
menyediakan objek UploadedFile; parser boundary dan perilaku proxy/deployment
tetap perlu diuji melalui Burp/curl pada lingkungan pengujian.

## Rekomendasi

Validasi struktur PDF pada ketiga jenis dokumen dengan parser yang sesuai dan
batas sumber daya; jangan mengandalkan substring signature atau MIME klien.
Jika kontrak mengharuskan nama `.pdf`, tambahkan allowlist ekstensi sebagai
lapisan kebijakan tambahan. Pertahankan nama server `.pdf`, penyimpanan privat,
dan konfigurasi penyajian yang tidak mengeksekusi upload. Validitas struktur
PDF tidak membuktikan keaslian bukti pembayaran.

Sesi ini menambahkan test dan catatan; implementasi backend belum diubah.
