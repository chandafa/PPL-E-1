# Data demo E-1

Dataset utama D-2 mengikuti `database.sqlite`: tiga kontrak, empat termin, satu parameter pajak, dua invoice dan dua riwayat penerbitan. Gunakan `D2_DRIVER=mock`, `D2_MOCK_DATASET=sqlite`, lalu jalankan `php artisan demo:d2`. Panduan [snapshot SQLite D-2](D2_SOURCE_DEMO.md) memuat isi sumber, API E-1, PDF pasangan, dan langkah pencatatan serta pembayaran.

Tidak ada faktur/pembayaran E-1 yang ditanam otomatis oleh seeder snapshot. Nama proyek/klien dan rincian item yang tidak tersedia dari sumber tetap kosong. Total PPN dua invoice untuk Oktober 2026 adalah Rp27.500.000.

Dataset demo lama dengan 32 faktur dan 5 pembayaran tetap merupakan fixture legacy untuk database terpisah. Untuk menggunakannya, pilih `D2_MOCK_DATASET=legacy` pada database kosong yang khusus untuk legacy, jalankan migrasi dan seed dasar, kemudian `php artisan db:seed --class=DemoSeeder` serta `php artisan demo:samples`. Jangan menjalankan fixture legacy pada database snapshot SQLite; ID `inv-demo-001` berbenturan dan seeder menolak penimpaan data canonical.

Data buatan `inv-d2-local-*` dari versi sebelumnya tidak dihapus, tetapi dipisahkan dari katalog snapshot aktif. Dokumen pengujian tetap fiktif dan bukan bukti transaksi/DJP nyata.
