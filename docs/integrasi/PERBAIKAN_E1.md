# Perbaikan milik E-1

Pembaruan 31 Oktober 2026 pada branch dev, dasar ea57e08 dengan perubahan working tree. Kode F-1 dan D-2 tidak ditambal. Temuan pemilik layanan ada pada [TEMUAN_F1_D2.md](TEMUAN_F1_D2.md); cara mengulang dan bukti pada [panduan integrasi](README.md).

| Bagian | Perbaikan E-1 | Verifikasi |
|---|---|---|
| Login | F1LoginController/F1ApiClient memanggil API F-1 v0.4, meneruskan emailOrUsername/password, memverifikasi JWT dan kecocokan identitas; respons token no-store. | Tes adapter, login browser dan bukti HTTP. |
| Otorisasi | Middleware memeriksa signature/exp dan status/peran terkini lewat F-1 bila F1_CHECK_ACCOUNT=true. Penolakan/gangguan tidak diganti token lokal. | Token hilang/rusak/expired 401; EMPLOYEE/inactive 403; F-1 putus 503; login kembali berhasil setelah pulih. |
| Invoice | projectId dari data.terkait dipertahankan, field bertentangan/salah tipe ditolak; tidak mengikuti redirect untuk meneruskan Bearer. Klien yang tidak dikirim tetap null. | HttpInvoiceProviderTest dan E1-04 pada backend D-2 resmi. |
| Route tiruan | Closure login placeholder dan invoice tiruan publik di E-1 dihapus agar request tidak keliru dianggap berasal dari produsen. | Tes route 404 dan log produsen HTTP. |
| Faktur pengganti | Pembatalan versi tengah A → B → C ditolak 409; versi terakhir dibatalkan dahulu, sehingga tidak ada dua faktur aktif. | CriticalLogicAuditTest; alur pengganti/pembatalan lain tetap lulus. |
| Rekap dan transaksi | Lock per invoice/periode dengan urutan tetap; refresh mengunci sebelum membaca sumber. Pembayaran memakai urutan periode → rekap → pembayaran. | Dua proses PHP/PostgreSQL: tidak ada snapshot lama menimpa hasil baru, dan pembayaran beririsan menghasilkan satu 200/satu 400. |
| Batas nominal | Total rekap yang melampaui decimal(18,2) ditolak 400 INVALID_CHANGE dan transaksi dibatalkan, bukan 500. | Regresi batas maksimum dan rollback. |
| Dokumen PDF | Header harus valid dan struktur dapat diparse serta memiliki halaman; penanda PDF dalam komentar PHP ditolak. Penyimpanan privat dan nama server tetap dipakai. | UploadFilenameTamperingTest; PDF asli tetap diterima, file palsu ditolak tanpa pembayaran/audit parsial. Bukan verifikasi keaslian DJP/bank. |
| Fixture dan dokumentasi | Test menggunakan fixture invoice yang benar; ekspektasi NTPN ganda/seed/sample disesuaikan dengan kebijakan saat ini. Panduan tidak lagi mengklaim invoice 006/007 atau Draft/pengganti otomatis pada seed. | 219 tes PHP lulus, 1.824 assertions. |
| Menjalankan proyek | Callback ComposerConfig yang salah diperbaiki menjadi Composer\\Config::disableProcessTimeout. | composer validate berhasil dan composer serve benar-benar dijalankan; /up merespons 200. |
| Kontrak | OpenAPI 0.2.1 menambah respons 502/503 F-1 dan enum terkait, tetap 11 operasi bisnis. Referensi respons dan koleksi dependensi diperbarui. | 21 respons JSON aktual cocok skema; ekspor PDF/XLSX terbaca dan total PPN cocok. |

Build frontend berhasil; empat tes JavaScript lulus. Tampilan login F-1 diperiksa pada desktop dan ponsel 390 px dengan komponen aplikasi yang sudah ada. Format dokumen Word dosen dipertahankan dan diperiksa terpisah.

OBS-02 tentang koreksi pajak setelah pembayaran yang menghasilkan selisih positif tetap memerlukan keputusan kebijakan; status CONFIRMED mengikuti kelengkapan dokumen. Tidak ditambahkan larangan koreksi sah atau aturan hak objek yang belum disepakati. Pengujian tidak membuktikan beban produksi, DJP, bank, aplikasi E-2, atau persetujuan pasangan.

Pemeriksaan ulang: .env utama semula masih mock dan empat migrasi E-1 tertinggal. Kini koneksi HTTP aktif pada alamat utama 8080, migrasi diterapkan tanpa mengubah data lama, dan composer integration / integration:stop menjalankan serta menghentikan ketiga layanan. Restart tidak menjalankan seed ulang D-2. Bukti: [aktif-utama.json](bukti/2026-10-31T07-43-25-100Z/aktif-utama.json) dan [langkah kelompok](UJI_KELOMPOK.md).
