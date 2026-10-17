# Paket pengujian invoice baru

Siapkan batch baru:

```bash
php artisan demo:flow
```

Atau tentukan nama batch yang belum pernah digunakan:

```bash
php artisan demo:flow --batch=uji-baru-01
```

Alternatif seeder Laravel: `php artisan db:seed --class=InvoiceFlowSeeder`.
Setiap eksekusi tanpa nama batch membuat empat referensi invoice mock D-2 baru,
sembilan PDF, satu berkas bukan PDF, manifest, panduan, dan checklist dalam folder
batch. Belum ada faktur atau pembayaran E-1 yang disimpan, sehingga invoice siap
dipilih di dropdown. Seeder demo biasa melewati invoice ini (`manualUploadOnly`).

Buka `PANDUAN.md` dalam folder batch untuk pasangan invoice/PDF dan hasil yang
diharapkan. Kolom hasil aktual pada `CHECKLIST.csv` sengaja kosong untuk pengujian
Anda. `VALIDASI.json`, bila tersedia, mencatat pemeriksaan pratinjau tanpa penyimpanan.

Batch yang sudah ada ditolak agar tidak menimpa dokumen atau data pengujian lama.
Untuk mengulang flow dari awal gunakan batch baru, tanpa `migrate:fresh`.
Perintah hanya tersedia di luar production dengan `D2_DRIVER=mock`.

Dokumen adalah simulasi, bukan dokumen pajak atau bukti pembayaran resmi.
