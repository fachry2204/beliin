# InvoFlow — Sistem Invoice & Barang Masuk

InvoFlow adalah aplikasi modern-monolith Laravel + Vue untuk mengelola pelanggan, supplier, barang, penerimaan stok, invoice, pembayaran sebagian/pelunasan, piutang, laporan, PDF, Excel, dan audit aktivitas. Seluruh kalkulasi keuangan dihitung realtime di Vue dan selalu dihitung ulang oleh Laravel sebelum disimpan.

## Fitur

- Authentication, rate limiting, role dan permission (Super Admin, Admin, Finance, Staff, Pimpinan).
- Master pelanggan, supplier, kategori dan barang dengan pencarian/pagination server-side.
- Barang masuk draft/final, weighted average purchase price, stok dan stock movement atomik.
- Invoice qty atau qty × volume; diskon persen/nominal; pajak; ongkir; modal dan laba.
- Nomor `INV/{YEAR}/{MONTH}/{SEQUENCE}` dengan transaction dan row locking.
- Penerbitan, pembatalan, pembayaran sebagian, pelunasan, piutang dan jatuh tempo otomatis.
- Preview/print A4, QR verifikasi, PDF; harga beli tidak pernah muncul pada invoice pelanggan.
- Dashboard, laporan penjualan/modal/laba/pajak/piutang, export CSV/XLSX/PDF.
- Redis untuk cache, session, queue dan job pembuatan PDF; audit log untuk perubahan penting.

## Teknologi

PHP 8.2+ (kompatibel PHP 8.3), Laravel 12, MySQL 8/InnoDB, Redis 6+, Vue 3 Composition API, Inertia.js, TypeScript strict, Tailwind CSS, Vite, DomPDF, Laravel Excel, Simple QR Code dan Spatie Permission.

## Persyaratan server

- PHP 8.2+ dengan `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`.
- MySQL 8+ dan Redis 6+.
- Composer 2, Node.js 20+ dan npm.
- Web root diarahkan ke folder `public/`; direktori `storage/` dan `bootstrap/cache/` dapat ditulis.

## Instalasi lokal

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
```

Buat database MySQL:

```sql
CREATE DATABASE invoflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'invoflow'@'localhost' IDENTIFIED BY 'ganti-password-kuat';
GRANT ALL PRIVILEGES ON invoflow.* TO 'invoflow'@'localhost';
```

Atur `DB_*` dan `REDIS_*` pada `.env`, kemudian:

```bash
php artisan migrate --seed
php artisan storage:link
npm run build
```

Akun awal mengikuti `SUPER_ADMIN_EMAIL` dan `SUPER_ADMIN_PASSWORD` di `.env`. Nilai default hanya untuk lokal dan wajib diganti sebelum production.

## Menjalankan aplikasi

```bash
composer run dev
```

Atau jalankan proses terpisah:

```bash
php artisan serve
npm run dev
php artisan queue:work redis --tries=3 --timeout=120
php artisan schedule:work
```

Scheduler menandai invoice lewat jatuh tempo setiap hari. Production sebaiknya menggunakan Supervisor/systemd untuk queue worker dan cron berikut:

```cron
* * * * * cd /var/www/invoflow && php artisan schedule:run >> /dev/null 2>&1
```

## Pengujian dan kualitas

Test memakai SQLite in-memory dan queue/cache terisolasi, sehingga tidak menyentuh database development.

```bash
php artisan test
vendor/bin/pint --test
npm run build
```

Test mencakup login, authorization, CRUD master, finalisasi barang masuk, stok, kalkulasi invoice, nomor invoice, pembayaran sebagian/pelunasan, invoice jatuh tempo, larangan menghapus invoice terbit, privasi harga beli, izin laba dan audit log.

## Deployment production

1. Gunakan `APP_ENV=production`, `APP_DEBUG=false`, HTTPS dan password/secret unik.
2. Jalankan `composer install --no-dev --optimize-autoloader` dan `npm ci && npm run build`.
3. Jalankan `php artisan migrate --force`, `php artisan storage:link`, `php artisan optimize`.
4. Jalankan queue worker Redis dan scheduler dengan process manager; restart worker setelah deploy (`php artisan queue:restart`).
5. Terapkan permission filesystem minimum dan jangan expose `.env`, `storage` privat, atau source code melalui web server.

## Backup dan pemulihan

```bash
mysqldump --single-transaction --routines --triggers -u invoflow -p invoflow > invoflow-$(date +%F).sql
tar -czf invoflow-storage-$(date +%F).tar.gz storage/app
```

Simpan backup terenkripsi di lokasi terpisah, tetapkan retensi, dan uji restore secara berkala:

```bash
mysql -u invoflow -p invoflow < invoflow-2026-07-15.sql
```
