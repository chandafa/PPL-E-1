# Data demo E-1

Dataset utama D-2 mengikuti `database.sqlite`: tiga kontrak, empat termin, satu parameter pajak, dua invoice dan dua riwayat penerbitan. Gunakan `D2_DRIVER=mock`, `D2_MOCK_DATASET=sqlite`, lalu jalankan `php artisan demo:d2`. Panduan [snapshot SQLite D-2](D2_SOURCE_DEMO.md) memuat isi sumber, API E-1, PDF pasangan, dan langkah pencatatan serta pembayaran.

`php artisan db:seed` pada mode snapshot SQLite hanya menanam data invoice referensi D-2, termasuk `inv-demo-sample` untuk `prj-demo-001` bertanggal 1 November 2026, masa pajak 2026-11, DPP Rp100.000.000, PPN Rp11.000.000, PPh Rp2.000.000. Tidak ada e-Faktur atau pembayaran yang dibuat; proses pencatatan dilakukan terpisah melalui aplikasi.

Invoice `inv-demo-001` tetap berada pada 28 Oktober 2026 dengan PPN Rp16.500.000. `e-billing-2026-11-001.pdf` adalah nama untuk dokumen contoh yang dapat dibuat manual, bukan pembayaran yang otomatis disimpan oleh seeder.

Dataset demo lama dengan 32 faktur dan 5 pembayaran tetap merupakan fixture legacy untuk database terpisah. Untuk menggunakannya, pilih `D2_MOCK_DATASET=legacy` pada database kosong yang khusus untuk legacy, jalankan migrasi dan seed dasar, kemudian `php artisan db:seed --class=DemoSeeder` serta `php artisan demo:samples`. Jangan menjalankan fixture legacy pada database snapshot SQLite; ID `inv-demo-001` berbenturan dan seeder menolak penimpaan data canonical.

Data buatan `inv-d2-local-*` dari versi sebelumnya tidak dihapus, tetapi dipisahkan dari katalog snapshot aktif. Dokumen pengujian tetap fiktif dan bukan bukti transaksi/DJP nyata.
