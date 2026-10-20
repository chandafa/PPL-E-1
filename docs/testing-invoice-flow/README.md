# Arsip paket pengujian invoice

Pembuat batch lama beserta seedernya dihapus pada 20 Oktober 2026.
Alur aktif untuk setiap laptop:

```bash
php artisan migrate
php artisan db:seed
php artisan demo:samples
```

PDF terbaru tersedia di `docs/postman/samples/`, termasuk invoice manual.
Gunakan `manifest.json` untuk mencocokkan PDF dengan invoice, dan `validation.json`
untuk hasil pembacaan parser. Ikuti [panduan demo](../panduan/DEMO_SEEDER.md).

Batch, template, checklist, dan laporan di folder ini adalah arsip pengujian lama.
Petunjuk pembuatan batch dalam dokumen arsip tidak berlaku lagi. Referensi invoice
manual yang sudah ada dipertahankan dan memiliki PDF dalam generator terbaru.

Semua dokumen adalah data fiktif untuk pengujian.
