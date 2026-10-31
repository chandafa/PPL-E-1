# Bukti integrasi lokal E-1, F-1 dan D-2

31 Oktober 2026. Sumber: server demo resmi F-1 0feb37a dan backend database D-2 b93fc4d. Kode upstream tidak diubah. E-1 dev ea57e08 ditambah perubahan working tree; belum commit/push pada tugas ini.

- [Aplikasi utama 8080](aktif-utama.json): 18 pemeriksaan lulus; login/cek akun F-1, invoice HTTP D-2, .env aktif, dan seluruh data lama E-1 tetap.
- [PDF siap diuji kelompok](main-setup/manifest.json): invoice D-2 15 Desember 2026; DPP 150 juta, PPN 16,5 juta, PPnBM jasa nol. Faktur utama belum disimpan.
- [42 kasus request dan 57 pemeriksaan](bukti-http.json): semua pemeriksaan alur E-1 lulus; lima observasi akses upstream belum sesuai harapan.
- [Regresi PHP](regresi.json): 219 tes lulus, 1.824 assertions. Empat tes JavaScript dan build juga lulus.
- [Validasi kontrak](validasi-kontrak.json): 21 respons JSON cocok OpenAPI E-1 0.2.1, 11 operasi dicakup termasuk ekspor PDF/XLSX terbaca.
- [Korelasi](korelasi.json), [log F-1](f1-requests.ndjson), [log D-2](d2-requests.ndjson): requestId UI-01/02 cocok dengan panggilan produsen.
- [Konkurensi dua proses](konkurensi.json): tidak terjadi stale overwrite; pembayaran beririsan menghasilkan satu 200/satu 400.
- [Pemulihan/restart](pemulihan.json): composer integration:stop lalu composer integration kembali siap; invoice D-2 tetap tanpa seed ulang.
- [Versi dan hash kode](versi-sumber.json) serta [pemeriksaan format Word](pemeriksaan-format.json): 107 file Pertemuan 1–5 tetap, properti template dosen tetap.
- [Input PDF dan manifest](input/manifest.json): invoice/PDF 11 Oktober 2026, masa Oktober; lampiran pembayaran contoh November. Semua fiktif dan terbaca; bukan verifikasi DJP/bank.

Screenshot: [login utama](UI-05-login-utama.png), [pratinjau utama](UI-04-utama-siap-uji.png), [pratinjau suite](UI-01-pratinjau.png), [detail tersimpan](UI-02-tersimpan.png), [D-2 terputus](UI-03-d2-putus.png).

Panduan aktif dan pembagian temuan pemilik berada di PPL-E-1/docs/integrasi/README.md dan TEMUAN_F1_D2.md. Tidak ada review dua arah, konsumsi aplikasi E-2, deployment produksi, atau load test yang diklaim. Observer tidak mengubah handler/respons upstream dan tidak menyimpan token/password.
