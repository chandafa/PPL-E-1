# Perbaikan audit pembayaran API-KE01-11

Perbaikan menggunakan **opsi B** dari [laporan temuan](LAPORAN_TEMUAN_KEAMANAN_API_KE01_11.md), mengikuti kontrak `openapi.yaml` yang mengizinkan koreksi NTPN/bukti pembayaran terkonfirmasi.

- Pembuatan pembayaran, pelengkapan draft, dan koreksi dicatat sebagai `CREATE`, `COMPLETE`, dan `CORRECT` pada `riwayat_perubahan_pembayaran`.
- Setiap catatan menyimpan akun aktor, waktu UTC, serta snapshot sebelum/sesudah termasuk NTPN dan URI berkas. Bukti lama tetap disimpan dan dapat ditelusuri melalui snapshot; identitas pembuat pertama tetap utuh.
- Pembayaran, audit, dan rekap disimpan dalam satu transaksi. Kegagalan transaksi membersihkan berkas baru. Penguncian baris rekap lalu pembayaran memastikan koreksi bersamaan membaca versi terbaru sebelum membuat snapshot.
- Trigger PostgreSQL menolak UPDATE/DELETE pada riwayat, mengikuti pola audit faktur.

Terapkan migrasi dengan `php artisan migrate` sebelum menjalankan versi aplikasi ini. Tidak ada perubahan bentuk respons API.

Riwayat baru tersedia melalui relasi model `PembayaranPajak::history()`. Pembayaran lama tidak diberi riwayat buatan; koreksi pertama setelah migrasi merekam kondisi tersimpan sebagai snapshot awal. Perubahan dan berkas yatim sebelum migrasi tidak dapat dipulihkan otomatis.

Pengujian regresi ada pada `tests/Feature/TaxPaymentTest.php`, termasuk rantai snapshot lintas aktor, pembayaran periode lampau, rollback saat audit gagal, dan proteksi riwayat di PostgreSQL.
