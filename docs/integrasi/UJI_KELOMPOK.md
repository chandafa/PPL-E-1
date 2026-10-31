# Uji integrasi kelompok E-1

Konfigurasi aplikasi utama sudah terhubung ke repo resmi F-1 dan D-2. Perbaikan hanya di E-1. Kode, kontrak, dan seed upstream dibiarkan sebagaimana versi pemilik; temuan ada di [TEMUAN_F1_D2.md](TEMUAN_F1_D2.md).

## Jalankan

Dari folder PPL-E-1 di Windows:

```powershell
composer integration
```

Buka **http://127.0.0.1:8080/login**. Akun fiktif resmi F-1: **super.admin**, kata sandi **Password123!**. Login ini memanggil server F-1 pada 4100. Invoice dibaca melalui HTTP D-2 pada 8026. Form/endpoint akun demo E-1 dimatikan.

Jika ketiga layanan sudah berjalan, langsung buka alamat tersebut. Untuk berhenti: composer integration:stop. Setelah berhenti, composer integration memakai database yang sama dan tidak menanam ulang seed D-2. Pada laptop baru, clone/install ketiga repo dan siapkan PostgreSQL menurut [panduan lengkap](README.md).

## Contoh siap diuji di laptop ini

| Masukan | Nilai |
|---|---|
| ID invoice D-2 | inv-6ae59e3b342a5 |
| Tanggal invoice dan e-Faktur | 15 Desember 2026 |
| Masa pajak | 2026-12 |
| Proyek | prj-demo-001 |
| DPP / PPN / PPnBM jasa | 150000000.00 / 16500000.00 / 0.00 |
| PDF e-Faktur | [efaktur.pdf](bukti/2026-10-31T07-43-25-100Z/main-setup/efaktur.pdf) |
| PDF pengganti dan lampiran | [Manifest](bukti/2026-10-31T07-43-25-100Z/main-setup/manifest.json) |

ID tersebut diterbitkan API D-2 pada database integrasi lokal, bukan ditambahkan ke kode/seeder mereka. Pada laptop lain, gunakan ID invoice yang benar-benar diterbitkan D-2 di laptop tersebut; ID ini bukan data global. Tanggal dan nominal PDF disusun dari respons HTTP invoice. Semua dokumen/transaksi fiktif; lampiran contoh masa Desember bertanggal Januari 2027.

1. Login, buka Catat e-Faktur, lalu masukkan ID invoice dan unggah PDF pada tabel.
2. Klik Periksa PDF. Harapan: masa Desember 2026, DPP Rp150 juta, PPN Rp16,5 juta, PPnBM nol.
3. Klik Simpan Faktur saat kelompok siap mencoba pencatatan. Contoh ini baru dipratinjau oleh penguji; belum disimpan pada database E-1 utama.
4. Periksa detail: invoice/proyek tertaut dan aktor memakai UUID akun F-1. Buka rekap 2026-12; perhatikan total merupakan gabungan dengan transaksi lama yang masih aktif.
5. Coba koreksi, riwayat, pembayaran, kelengkapan NTPN/bukti, ekspor, dan retry sesuai API. Invoice yang sudah mempunyai faktur aktif tidak boleh disimpan ulang tanpa pengganti.

## Hasil yang sudah diverifikasi

- [Aplikasi utama](bukti/2026-10-31T07-43-25-100Z/aktif-utama.json): 18 pemeriksaan lulus; .env HTTP aktif, login F-1, pratinjau D-2 dari browser, login demo mati, dan data lama tetap.
- [Screenshot pratinjau utama](bukti/2026-10-31T07-43-25-100Z/UI-04-utama-siap-uji.png).
- [Suite terpisah](bukti/2026-10-31T07-43-25-100Z/bukti-http.json): 42 kasus request dan 57 pemeriksaan E-1 lulus; lima observasi akses upstream belum sesuai.
- [Regresi](bukti/2026-10-31T07-43-25-100Z/regresi.json): 219 tes / 1.824 assertions lulus.
- [Restart](bukti/2026-10-31T07-43-25-100Z/pemulihan.json): layanan kembali aktif dan invoice D-2 tetap sama tanpa seed ulang.

Kesalahan upstream dicatat untuk dilaporkan, bukan ditambal oleh E-1. F-1 yang tersedia pada repo adalah server demo Node dengan JWT asli dan state memori; D-2 adalah backend Laravel/database dengan D-1 internal masih mock. Integrasi ini tidak membuktikan deployment produksi atau review pasangan.
