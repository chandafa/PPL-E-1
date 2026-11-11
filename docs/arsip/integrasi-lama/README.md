# Arsip pengujian integrasi 31 Oktober 2026

Skrip di folder ini memakai profil `.env.integration`, database E-1 terpisah, serta struktur runtime lama yang menyediakan database `tests`. Setup aktif sekarang dikelola oleh `RuntimeSetup` dan `Installer`, memakai database utama E-1 dan database D-2 terpisah.

Arsip dipertahankan untuk menelusuri bukti lama. Jangan menjalankannya pada runtime aplikasi utama. Runner browser hanya dapat dimulai setelah `E1_ENABLE_LEGACY_SUITE=1` ditetapkan secara sengaja; tetap diperlukan runtime lama yang terisolasi.

Pengujian aktif tersedia melalui `composer test`, `npm run test:unit`, dan `npm run test:ui`. Uji konkurensi dengan schema PostgreSQL terpisah tersedia di `scripts/audit-recap-concurrency.php`.
