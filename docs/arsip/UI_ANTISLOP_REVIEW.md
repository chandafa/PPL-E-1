# Verifikasi UI E-1 dan gate Antislop

Tanggal: 2026-10-17 (Asia/Jakarta). Mode: During, sesuai pilihan sesi.
Skill: antislop 3.2.21, ditambah UI, Copywriting, Human, dan Layoutmobile.

## Hasil yang dijalankan

| Pemeriksaan | Hasil dan bukti |
|---|---|
| PHPUnit pada PostgreSQL | PASS: 118 tes, 1.130 assertions; termasuk kontrak 11 operasi, rantai pengganti lintas periode, pembatalan pengganti, XLSX/PDF, summary E-2, rebuild rekap, serta kelengkapan dan keamanan seeder aditif. |
| JavaScript | PASS: 2 tes untuk presisi nominal maksimum, selisih, escaping HTML, dan pembatasan redirect lokal. |
| Build produksi | PASS: Vite menghasilkan manifest, CSS Tailwind, dan JavaScript lokal. |
| Browser Chromium | PASS: 7 skenario, 0 gagal, 0 skipped, 0 flaky pada run akhir. |
| Axe | PASS: 10 pemindaian dengan 0 pelanggaran aturan WCAG 2 A/AA dan WCAG 2.1 A/AA yang diperiksa. |
| Responsive | PASS: lebar 360, 768, 1024, dan 1440 px; tidak ada gulir horizontal pada halaman. Tabel memakai wadah gulir tersendiri. |
| Zoom | PASS: CSS zoom 200% pada viewport 768 px; menu masih dapat dibuka/ditutup dan halaman tidak meluber. |
| Kontras | PASS: perhitungan dengan skrip contrast-check dari antislop-human; pasangan utama tercantum di bawah. |
| Pemeriksaan PHP | PASS: Pint, kompilasi Blade, pemeriksaan sintaks file baru, dan composer validate. |
| Diff | PASS: git diff --check. |

Pemindaian mencakup login, daftar, pencatatan, detail, rekap, pembayaran, dialog ekspor,
dialog koreksi, dialog pembatalan, dan menu mobile saat zoom.

Bukti browser ada di `test-results/browser-results.json`, `test-results/accessibility.json`,
`test-results/recap-{360,768,1024,1440}.png`, dan `test-results/zoom-200.png`.
Folder tersebut diabaikan Git dan dihasilkan ulang oleh `npm run test:ui`.
Sumber skenario: `tests/browser/e1.spec.mjs`.

## Catatan interaksi yang diperiksa

| Kontrol | Hasil yang diamati |
|---|---|
| Masuk demo Admin, COO, CEO | Membuka daftar melalui verifikasi API. |
| Masuk dengan token F-1 | Membuka daftar; token peran PM ditolak dan dibersihkan. |
| Keluar dan token tidak sah | Menuju login; sessionStorage tidak menyimpan token yang ditolak. |
| Cari NSFP, proyek, klien, periode, status | Daftar berubah sesuai filter; hasil kosong mempunyai pesan; filter bertahan saat refresh. |
| Reset | Menghapus filter dan memuat daftar awal. |
| Halaman berikutnya/sebelumnya | Data API dari 32 faktur hasil seeder menampilkan 20 baris pada halaman pertama dan 12 pada halaman kedua; navigasi kembali berhasil. |
| Cari saat API 503 | Pesan kegagalan muncul; pencarian dapat diulang setelah koneksi dipulihkan. |
| Catat e-Faktur dan Kembali/Batal | Membuka pencatatan atau kembali ke daftar tanpa penyimpanan. |
| Periksa PDF dengan field kosong | Validasi wajib menandai input invoice. |
| Berkas bukan PDF | Penolakan ditampilkan dan Simpan tetap nonaktif. |
| Invoice tidak dikenal atau sudah aktif | Pesan API 404/409 muncul tanpa faktur parsial. |
| Ekstraksi PDF valid | Identitas, item, dan nominal tampil; mengganti berkas menonaktifkan Simpan sampai diperiksa lagi. |
| Simpan Faktur | Membuka detail faktur hasil penyimpanan. |
| Koreksi Faktur dan Simpan Koreksi | Dialog terbuka; perubahan PPnBM dan catatan muncul pada riwayat. |
| Tambah/Hapus Item serta Batal koreksi | Item bertambah/berkurang; Batal menutup dialog tanpa penyimpanan. |
| Lihat nilai sebelum dan sesudah | Snapshot riwayat dapat dibuka. |
| Catat Pengganti | Invoice terkunci; PDF disimpan tanpa pratinjau sesuai keterbatasan API. |
| Buka faktur asal/pengganti | Navigasi relasi berhasil; label Terganti/Pengganti sesuai data. |
| Batalkan dan konfirmasi | Status menjadi Dibatalkan, riwayat tetap terlihat. |
| Kembali dari pembatalan | Dialog tertutup tanpa perubahan status. |
| Tampilkan Rekap | Membuka masa pajak yang dipilih. |
| Ekspor dan Tutup | Dialog pratinjau dapat dibuka/ditutup. |
| Pratinjau PDF | Iframe menerima URL Blob PDF dari API. |
| Unduh Excel/PDF | Browser menerima nama berkas rekap-pajak-2026-11.xlsx dan .pdf. |
| Escape pada dialog ekspor | Dialog tertutup; fokus kembali ke tombol Ekspor. |
| Lengkapi dan Konfirmasi Pembayaran | NTPN/bukti tersimpan melalui PATCH multipart, status menjadi Terkonfirmasi. |
| Simpan Pembayaran tanpa NTPN/bukti | Catatan baru muncul sebagai Draft / Belum Konfirmasi. |
| Pembayaran periode kosong | Fieldset serta tombol Simpan nonaktif, alasan ditampilkan. |
| Sidebar, breadcrumb, identitas aplikasi | Navigasi menuju halaman yang tersedia. |
| Menu mobile, Tutup menu, Escape | Drawer dapat dibuka/ditutup; fokus kembali ke pemicu. |
| Respons 403 | Konten pajak disembunyikan dan akses ditolak ditampilkan. |

Tidak ada exception JavaScript yang tidak tertangani pada skenario akhir. HTTP 401,
403, 404, 409, dan 503 yang sengaja diuji merupakan respons kegagalan terkontrol,
bukan bukti bahwa seluruh respons HTTP selalu sukses.

## Gate 1: Hard Gate

- R-02 PASS: pemindaian teks baru pada template, JavaScript, CSS, dan panduan tidak menemukan em dash.
- R-03 PASS: empat viewport dan zoom diperiksa; overflow tabel dibatasi pada wadah fokusabel.
- R-17 PASS: kartu angka mengambil data API; pengujian memakai database demo terpisah.
- R-18 PASS: UI tidak memuat testimonial atau identitas pelanggan rekaan.
- R-23 PASS: arah layout berasal dari PDF; identitas memakai nama modul dan teks E1; akun berasal dari token terverifikasi.
- R-24 PASS: tujuan sidebar, breadcrumb, serta tautan relasi diuji melalui browser.
- R-25 PASS: axe color-contrast lulus; pasangan token utama dihitung dengan rumus WCAG.
- R-26 PASS: keluarga kontrol pada matriks interaksi mempunyai perilaku nyata; fitur PDF tersimpan disampaikan sebagai informasi tanpa tombol palsu.
- R-27 PASS: daftar diuji saat kosong, memuat, gagal 503, serta akses ditolak; periode kosong juga diuji.
- R-28 PASS: tidak ada FAQ tambahan pada aplikasi pajak ini.
- R-32 PASS: axe, Tab, Escape, pemulihan fokus, dan akses menu saat zoom diuji.
- R-33 PASS: fitur ditulis langsung pada Blade/CSS/JavaScript; skrip Solar hanya mengekstrak aset ikon.
- R-34 PASS: tema terang yang dikirim diperiksa; tidak ada toggle tema yang menjanjikan mode lain.
- R-35 PASS: build dijalankan dan 7 skenario browser lulus; matriks di atas mencatat aksi dan hasil.
- R-36 PASS: UI menyampaikan data pajak dari API dan batasan nyata; tidak ada klaim keamanan, pelanggan, atau performa rekaan.
- R-37 PASS: Design Read dan dial 1/1/1 tercantum pada rencana serta UI_BLADE.md sebelum verifikasi.
- R-38 PASS: referensi yang tidak ada berlabel Belum tersedia; contoh data dipisahkan sebagai demo.

## Gate 2: Purpose Gate

- R-01 PASS: navy mengikuti arah PDF dan menandai aksi utama; latar solid membantu pemeriksaan data.
- R-04 PASS: Solar Linear diminta pengguna; simbol dipilih untuk dokumen, pencarian, pembayaran, dan ekspor.
- R-06 PASS: font sistem menjaga keterbacaan; tabular numerals menyelaraskan digit nominal.
- R-07 PASS: latar tidak memakai pola dekoratif.
- R-08 PASS: panah hanya membantu navigasi kembali, breadcrumb, dan pagination.
- R-09 PASS: badge menyatakan status faktur, penggantian, atau pembayaran.
- R-10 PASS: permukaan solid digunakan; tidak ada glassmorphism bertumpuk.
- R-12 PASS: shadow dipakai pada dialog untuk menjelaskan lapisan, bukan pada setiap panel.
- R-13 PASS: tidak ada glow dekoratif.
- R-14 PASS: panel input mengikuti tahap invoice, PDF, dan ekstraksi; metrik rekap mengelompokkan angka periode.
- R-19 PASS: interaksi hanya memakai transisi pendek; loading memakai teks dan skeleton statis.
- R-22 PASS: tidak ada ilustrasi pengisi yang tidak berkaitan dengan pekerjaan pajak.

## Gate 3: Liveliness

- Dial PASS: ENERGY 1 / RHYTHM 1 / MOTION 1 dinyatakan pada panduan desain.
- Konsistensi dial PASS: layout administratif stabil, hierarki konsisten, dan tidak ada animasi dekoratif berulang.
- Focal point PASS: daftar untuk menemukan faktur, formulir untuk pencatatan, dan rekap untuk memeriksa total/pembayaran.
- Whitespace PASS: jarak memisahkan tahap formulir, ringkasan, daftar faktur, serta pembayaran.
- Aksen PASS: navy memprioritaskan aksi utama; warna status mempunyai makna operasional.
- Motif identitas PASS: label Masa pajak, NSFP, DPP/PPN/PPnBM, dan nominal tabular mengikuti alur modul E-1.
- Design Read PASS: arah aplikasi administrasi pajak dan audiensnya bersumber dari PDF serta pilihan pengguna.

## Gate 4: Craftsmanship dan Quality Locks

- C-1 PASS: alasan warna, layout, font, ikon, spacing, badge, serta card ditulis di UI_BLADE.md.
- C-2 PASS: matriks interaksi mencatat kontrol yang dijalankan; aksi yang belum didukung API tidak dibuat sebagai tombol.
- C-3 PASS: enam layar mengikuti pekerjaan pencatatan dan pelaporan dalam PDF.
- C-4 PASS: axe, keyboard, status gagal/kosong, zoom, dan empat viewport diperiksa.
- C-5 PASS: angka aplikasi berasal dari API; screenshot bukti berasal dari database demo pengujian.
- R-05 PASS: susunan mengikuti dokumen E-1; tidak ditambah hero, grafik, atau aktivitas fiktif pada layar operasional.
- R-11 PASS: radius membedakan kontrol, panel, dan dialog; tidak semua komponen berbentuk pill.
- R-15 PASS: label tindakan menyebut pencatatan, koreksi, pembatalan, pembayaran, dan unduhan.
- R-16 PASS: teks UI menyebut operasi pajak dan hasilnya, tanpa klaim pemasaran generik.
- R-20 PASS: struktur serta kolom berpusat pada NSFP, invoice, masa pajak, dan status pembayaran E-1.
- R-21 PASS: tema terang tetap mengikuti PDF untuk pemeriksaan angka dan dokumen cetak.
- R-29 PASS: navy dan netral membentuk palet utama; warna semantik dibatasi untuk status.
- R-30 PASS: layout mengadaptasi PDF pengguna; shadcn hanya menjadi acuan komponen Blade.
- R-31 PASS: setiap keputusan visual utama mempunyai alasan satu baris pada panduan.

## Kontras terukur

| Pasangan | Rasio | Ambang yang diperiksa | Hasil |
|---|---:|---:|---|
| Putih pada primary #23465f | 9,94:1 | 4,5:1 | PASS |
| Muted #536675 pada #f4f6f8 | 5,50:1 | 4,5:1 | PASS |
| Batas input #7c8c98 pada putih | 3,46:1 | 3:1 untuk komponen | PASS |
| Fokus #316b99 pada #f4f6f8 | 5,25:1 | 3:1 untuk indikator | PASS |
| Normal #334d62 pada #f1f5f9 | 8,06:1 | 4,5:1 | PASS |
| Pengganti #274b79 pada #e5edf7 | 7,51:1 | 4,5:1 | PASS |
| Dibatalkan #942722 pada #fcebea | 7,08:1 | 4,5:1 | PASS |
| Draft #775000 pada #fff0ce | 6,35:1 | 4,5:1 | PASS |
| Terkonfirmasi #24563d pada #e5f1eb | 7,32:1 | 4,5:1 | PASS |
| Terganti #44596b pada putih | 7,27:1 | 4,5:1 | PASS |
| Error #942722 pada #fff1f0 | 7,42:1 | 4,5:1 | PASS |

## Batas verifikasi

Browser dan zoom diuji melalui Chromium desktop/emulasi, bukan perangkat sentuh fisik
atau sesi pembaca layar dengan audio. Axe memeriksa aturan otomatis, bukan sertifikasi
aksesibilitas menyeluruh. Integrasi langsung F-1 dan D-2 belum tersedia; pengujian memakai
token demo terverifikasi dan provider D-2 mock. CI telah diperluas tetapi belum dijalankan
di GitHub pada sesi ini. Bukti di atas berasal dari eksekusi lokal.
