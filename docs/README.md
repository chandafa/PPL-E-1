# Dokumentasi E-1

[README proyek](../README.md) memuat instalasi, konfigurasi, dan alur penggunaan.
Indeks ini mengelompokkan dokumen acuan, panduan aktif, paket pengujian, dan arsip.

## Mulai dari sini

| Kebutuhan | Dokumen |
|---|---|
| Menjalankan demo dengan data contoh | [Panduan data demo](panduan/DEMO_SEEDER.md) |
| Memahami halaman dan alur web | [Panduan UI Blade](panduan/UI_BLADE.md) |
| Mencoba API melalui Postman | [Panduan Postman](api/POSTMAN.md) |
| Melihat respons sukses dan galat | [Referensi respons API](api/RESPON_API.md) |
| Memeriksa kontrak mesin | [OpenAPI](../openapi.yaml) |
| Menjalankan pengujian unggah dari awal | [Paket pengujian invoice](testing-invoice-flow/README.md) |

## Acuan dan keputusan demo

| Dokumen | Kedudukan |
|---|---|
| [Spesifikasi fitur minimum v0.2](acuan/01_Spesifikasi_Fitur_Minimum_E-1_v0.2.docx.pdf) | Dokumen sumber asli. |
| [Rancangan antarmuka inti v0.2](acuan/02_Rancangan_Antarmuka_Inti_E-1_v0.2.docx.pdf) | Dokumen sumber asli. |
| [Kontrak API final v0.2](acuan/Kontrak_API_Final_Kelas_E_Kelompok_1.docx.pdf) | Dokumen sumber asli. |
| [Adendum demo v0.3](acuan/ADENDUM_DEMO_E1_v0.3.md) / [PDF](acuan/ADENDUM_DEMO_E1_v0.3.pdf) | Keputusan demo lokal, periode kosong, pembayaran Draft, dan penyelarasan nomor API. Dibaca bersama dokumen sumber. |

`openapi.yaml` adalah kontrak mesin yang digunakan pengujian otomatis. Adendum
mencatat keputusan pengguna untuk demo lokal; bukan bukti persetujuan mitra atau
pengujian integrasi langsung F-1, D-2, dan E-2.

## Koleksi dan bukti pengujian

- [Koleksi Postman](postman/E1-API.postman_collection.json) dan [environment lokal](postman/E1-Local.postman_environment.json).
- PDF contoh Postman berada di `postman/samples/`; buat ulang dengan `php artisan demo:samples`.
- [Panduan paket invoice](testing-invoice-flow/README.md), [template panduan](testing-invoice-flow/PANDUAN_TEMPLATE.md), dan [template checklist](testing-invoice-flow/CHECKLIST_TEMPLATE.csv).
- Batch pengujian dan manifest berada di `testing-invoice-flow/<batch>/`; buat batch baru dengan `php artisan demo:flow`. Laporan lokal, bila tersedia, berada di `testing-invoice-flow/HASIL_VERIFIKASI.md`.

Catatan hasil pengujian berlaku pada waktu eksekusi yang tercantum. Checklist yang
belum diisi bukan bukti bahwa pengujian sudah lulus. Setiap batch dipertahankan
bersama manifest, dokumen contoh, dan hasil validasinya.

`docs/.gitignore` mengabaikan dokumen contoh, folder batch pengujian, dan laporan
verifikasi lokal. Panduan, template, dokumen acuan, koleksi, serta environment
Postman tetap disertakan. Aturan ignore tidak mengeluarkan file yang sudah
terlacak dari Git. Tes browser membuat fixture di
`storage/framework/testing/e1-browser-samples/` melalui setup Playwright.

## Arsip

- [Verifikasi UI dan Antislop terdahulu](arsip/UI_ANTISLOP_REVIEW.md).
- [Rencana implementasi API 13 Oktober 2026](arsip/rencana/2026-10-13-e1-api.md).

Arsip menyimpan konteks dan hasil historis, bukan status pengujian terbaru.

## Memperbarui dokumen hasil generator

Jalankan dari akar proyek:

```bash
php scripts/generate-api-reference.php
php scripts/generate-demo-contract-addendum.php
```

Perintah pertama menghasilkan `docs/api/RESPON_API.md` dari `openapi.yaml`.
Perintah kedua menghasilkan `docs/acuan/ADENDUM_DEMO_E1_v0.3.pdf` dari sumber
Markdown di folder yang sama. Edit sumbernya sebelum membuat ulang hasil generator.
