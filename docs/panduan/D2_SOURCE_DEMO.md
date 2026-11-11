# Seeder D-2 dari database.sqlite untuk E-1

Seeder E-1 menggunakan snapshot lima tabel bisnis dari `database.sqlite` yang diberikan. Data sudah dianggap diterima dari D-2; API dan pengolahannya berjalan di E-1 tanpa server D-2. Aplikasi tetap memakai PostgreSQL, tidak membutuhkan file/driver SQLite saat dijalankan.

Sumber SHA-256: `98557394b4aebef3dfbcf1e674cb6715e8f6febdcbee9fbb924d361a43c9221e`. Snapshot tersimpan di `database/data/d2-source-demo.json`. File SQLite asli tidak diubah; tabel akun, sesi, cache, dan antrean tidak disalin.

## Menyiapkan data

Pada lingkungan lokal gunakan:

```dotenv
APP_ENV=local
D2_DRIVER=mock
D2_MOCK_DATASET=sqlite
```

Lalu jalankan:

```bash
php artisan config:clear
php artisan demo:d2
```

Perintah tersebut menjalankan migrasi tanpa reset, memasang seeder `D2SourceDemoSeeder`, dan membuat PDF pasangan di `storage/app/private/d2-source-samples`. `php artisan db:seed` memasang referensi invoice saja; e-Faktur dan pembayaran tidak disimpan otomatis. `php artisan integration --d2=mock` menyiapkan snapshot tanpa menanam transaksi E-1; jalankan layanan dengan `php artisan integration run --d2=mock` untuk memakai login F-1.

Untuk menjalankan hanya E-1 dengan login demo lokal, gunakan pula `E1_DEV_LOGIN=true`, `F1_API_ENABLED=false`, dan `F1_CHECK_ACCOUNT=false`; pertahankan konfigurasi database, APP_KEY, dan kunci JWT lokal sesuai README, lalu jalankan `composer serve`. Hentikan runtime sebelumnya dengan `php artisan integration:stop` jika port 8080 masih dipakai.

## Isi sumber yang dipertahankan

Terdapat 3 kontrak, 4 termin, 1 parameter pajak, 2 invoice, dan 2 riwayat penerbitan. ID bisnis, relasi, status, nominal, tanggal, pencatat, dan timestamp sumber dipertahankan. ID numerik riwayat adalah surrogate lokal: seeder memakai kunci bisnis invoice/status/waktu agar tidak menimpa ID riwayat dataset lama.

| ID invoice | Nomor invoice | Termin | Tanggal | DPP | PPN | PPh | Total komersial |
|---|---|---|---|---:|---:|---:|---:|
| inv-demo-sample | INV-2026-SAMPLE | trm-demo-sample | 2026-11-01 | 100.000.000 | 11.000.000 | 2.000.000 | 109.000.000 |
| inv-demo-001 | INV-2026-0002 | trm-demo-002 | 2026-10-28 | 150.000.000 | 16.500.000 | 3.000.000 | 163.500.000 |

Keduanya berstatus `TERTAGIH` dan proyek `prj-demo-001`; `inv-demo-sample` berada pada masa November 2026 dan `inv-demo-001` pada masa Oktober 2026. `trm-demo-002` berstatus `SUDAH_DITAGIHKAN`, dengan jatuh tempo 15 Desember 2026 sebagaimana sumber; tanggal ini tidak disamakan dengan tanggal invoice.

Nama proyek, identitas/nama klien, dan rincian jasa tidak tersedia di SQLite. API mengembalikan metadata tersebut sebagai `null` serta `items=[]`. Tidak menambahkan klien/nama proyek/rincian jasa fiktif ke sumber. PPN 11% dan PPh 2% mengikuti parameter pada snapshot.

## API E-1

Semua endpoint memakai host E-1 dan Bearer token yang sudah didukung aplikasi. Pada mode demo lokal, token dapat diperoleh dari `POST /api/dev/f1/login` dengan `{"akunId":"AKN-0002","kodePeran":"Admin"}`.

| Endpoint | Fungsi |
|---|---|
| `GET /api/ui/d2/invoices/all` | Dua referensi invoice snapshot, termasuk yang telah diproses |
| `GET /api/ui/d2/invoices` | Referensi snapshot yang belum memiliki faktur aktif |
| `GET /api/ui/d2/invoices/{invoiceId}` | Detail invoice, kontrak, termin, parameter pajak, dan riwayat |
| `POST /api/v1/tax-invoices/extractions` | Periksa PDF untuk invoice yang dipilih |
| `POST /api/v1/tax-invoices/outgoing` | Catat faktur dari invoice tersebut |

Detail memakai `data.source=SQLITE_SNAPSHOT` dan menyertakan `sourceSha256`. ID di luar dataset aktif menghasilkan 404. Nama proyek/klien tidak dapat digunakan untuk pencarian karena tidak tersedia; gunakan ID proyek. API bisnis E-1 lainnya tetap sama.

[Koleksi Postman E-1](../postman/E1-D2-Local.postman_collection.json) berisi login demo, katalog, detail, periksa PDF, simpan faktur, dan detail hasil. Pilih PDF pasangan sebelum mengirim request multipart. Koleksi tidak memanggil host D-2.

## Memproses dengan fungsi E-1

1. Pilih `inv-demo-sample` pada Catat e-Faktur; invoice seed bertanggal 1 November 2026, DPP Rp100.000.000, dan PPN Rp11.000.000.
2. Unggah PDF e-Faktur yang sesuai, klik **Periksa PDF**, lalu **Simpan Faktur** untuk mencatatnya secara terpisah dari seeding.
3. Untuk faktur pengganti, gunakan `efaktur-<invoiceId>-pengganti.pdf`; PDF diperiksa saat penyimpanan.
4. Rekap November berisi `inv-demo-sample` dengan PPN **Rp11.000.000**; rekap Oktober berisi `inv-demo-001` dengan PPN **Rp16.500.000**.
5. Untuk e-Billing, gunakan PDF `e-billing-2026-11-001.pdf`; pembayaran baru tersimpan setelah dicatat melalui fitur pembayaran.

PDF adalah dokumen pengujian fiktif, bukan dokumen DJP/bank. Karena sumber tidak memiliki rincian item, PDF memakai satu baris berlabel **Item uji E-1 (bukan data D-2)**; baris ini tidak dimasukkan ke snapshot sumber. PPnBM nol merupakan nilai dokumen pengujian, bukan field dari SQLite. Empat PDF e-Faktur dan dua lampiran pembayaran dibaca ulang; `manifest.json` dan `validation.json` mencatat pemetaan, nominal, masa, NSFP dan hash.

## Data lama dan pengulangan

Enam invoice buatan `inv-d2-local-*` tetap disimpan, tetapi tidak muncul pada katalog/detail/pencarian snapshot aktif. Faktur, pembayaran, audit, serta berkas lama tidak dihapus. Jika folder sampel masih berisi PDF dataset lain, folder tersebut dipindah ke nama `d2-source-samples-legacy-<waktu>-<suffix>` sebelum keluaran snapshot dipublikasikan.

Seeder hanya menerima lingkungan local/testing dengan driver mock dan dataset sqlite. Pengulangan mempertahankan transaksi E-1. Benturan ID atau perubahan nilai sumber menyebabkan rollback seluruh penyesuaian. Seed bawaan lama pada status `trm-demo-002` hanya dapat diselaraskan jika belum ada faktur yang memakai referensi tersebut.

Fixture legacy memiliki `inv-demo-001` dengan nilai berbeda. `D2_MOCK_DATASET=legacy` hanya untuk database demo/pengujian terpisah; seeder legacy ditolak jika menemukan invoice canonical. Jangan menjalankan `DemoSeeder` legacy pada database snapshot ini. Data lama yang sudah menjadi transaksi E-1 tetap dapat muncul dalam rekap; angka penerimaan di atas berlaku untuk database yang belum berisi transaksi lain pada masa tersebut.

## Mengulang pemeriksaan

Jalankan tes PHP pada database pengujian PostgreSQL terpisah. Suite lama menggunakan mode legacy secara eksplisit; suite snapshot memeriksa data canonical.

```bash
source "$HOME/.nvm/nvm.sh"
nvm use default
E1_UI_TEST_DATABASE=e1_d2_source_ui_test npx playwright test --config=playwright.d2-source.config.mjs
```

Ikuti `.nvmrc` bila tersedia. Suite browser mengosongkan hanya database uji dengan awalan `e1_d2_source_`, menyiapkan dua invoice, dan menjalankan E-1 pada port 8089 dengan login lokal. Sesuaikan koneksi PostgreSQL pengujian; jangan menunjuk database aplikasi utama.

## Hasil validasi

3 November 2026, Linux/PostgreSQL: 246 tes PHP lulus (2.002 assertions), pemeriksaan format dan diff bersih. Browser Chromium berhasil membaca katalog dua invoice, memeriksa PDF, mencatat keduanya, membuat pengganti, memfilter proyek/masa, dan membayar Oktober dengan selisih nol. API aplikasi utama pada port 8080 menampilkan tepat dua invoice sumber; field bisnis dan timestamp hasil seed dibandingkan dengan fixture. Enam invoice buatan lama tetap tersimpan, tanpa penambahan faktur/pembayaran E-1 otomatis. SHA-256 SQLite asli tetap sama. Koleksi Postman disediakan sebagai contoh dan tidak diklaim dijalankan dengan runner Postman.
