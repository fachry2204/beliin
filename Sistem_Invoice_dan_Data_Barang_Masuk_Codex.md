# SISTEM PENCATATAN INVOICE DAN DATA BARANG MASUK

## 1. Tujuan Sistem

Bangun aplikasi web untuk:

- Membuat invoice penjualan.
- Mencetak dan mengunduh invoice dalam format PDF.
- Menyimpan seluruh riwayat invoice.
- Mencatat data barang masuk.
- Menyimpan harga beli dan harga jual.
- Menghitung subtotal, diskon, pajak, biaya kirim, dan grand total.
- Mencatat pembayaran invoice.
- Menghitung sisa tagihan.
- Menghitung modal dan laba kotor.
- Menyediakan dashboard dan laporan.

Sistem menggunakan arsitektur modern monolith.

---

# 2. Teknologi

Gunakan stack berikut:

## Backend

- Laravel
- PHP 8.3 atau versi kompatibel
- Laravel Eloquent ORM
- Laravel Validation
- Laravel Form Request
- Laravel Policy
- Laravel Middleware
- Laravel Queue
- Laravel Scheduler

## Frontend

- Vue 3
- Composition API
- Inertia.js
- TypeScript
- Tailwind CSS
- Vite

## Database

- MySQL
- Storage Engine: InnoDB
- Character Set: utf8mb4
- Collation: utf8mb4_unicode_ci

## Cache dan Queue

- Redis

Redis digunakan untuk:

- Cache dashboard.
- Cache laporan.
- Session.
- Queue pembuatan PDF.
- Queue notifikasi.
- Queue email.
- Rate limiting.

---

# 3. Arsitektur Sistem

Gunakan arsitektur:

```text
Browser
   │
   ▼
Vue 3 + TypeScript
   │
   ▼
Inertia.js
   │
   ▼
Laravel
   │
   ├── MySQL InnoDB
   ├── Redis
   ├── Queue
   └── File Storage
```

Tidak perlu memisahkan frontend dan backend menjadi dua aplikasi.

Laravel menangani:

- Authentication.
- Authorization.
- Routing.
- Business logic.
- Database.
- Validasi.
- Laporan.
- PDF.
- Queue.
- Cache.

Vue 3 digunakan untuk:

- Dashboard.
- Form invoice dinamis.
- Kalkulasi realtime.
- Data table.
- Filter.
- Modal.
- Grafik.

Semua kalkulasi keuangan wajib dihitung ulang oleh backend Laravel sebelum data disimpan.

---

# 4. Hak Akses

## 4.1 Super Admin

Akses penuh:

- Dashboard.
- Pengguna.
- Pelanggan.
- Supplier.
- Data barang.
- Barang masuk.
- Invoice.
- Pembayaran.
- Laporan.
- Pengaturan.
- Audit log.

## 4.2 Admin atau Finance

Dapat:

- Mengelola pelanggan.
- Mengelola supplier.
- Mengelola barang.
- Mencatat barang masuk.
- Membuat invoice.
- Mengubah invoice.
- Mencatat pembayaran.
- Mencetak invoice.
- Melihat laporan.

Tidak dapat:

- Menghapus Super Admin.
- Mengubah pengaturan inti sistem.

## 4.3 Staff

Dapat:

- Membuat invoice.
- Melihat invoice.
- Mencetak invoice.
- Menambah pelanggan.

Tidak dapat:

- Melihat harga beli.
- Melihat keuntungan.
- Menghapus transaksi.
- Mengubah pengaturan.

## 4.4 Pimpinan atau Viewer

Hanya dapat:

- Melihat dashboard.
- Melihat invoice.
- Melihat laporan.
- Melihat keuntungan.

Tidak dapat mengubah data.

---

# 5. Struktur Menu

```text
Dashboard

Master Data
├── Data Pelanggan
├── Data Supplier
├── Data Barang
└── Kategori Barang

Transaksi
├── Data Barang Masuk
├── Buat Invoice
├── Semua Invoice
├── Pembayaran
└── Piutang

Laporan
├── Laporan Invoice
├── Laporan Penjualan
├── Laporan Barang Masuk
├── Laporan Modal
├── Laporan Keuntungan
├── Laporan Pajak
└── Laporan Piutang

Pengaturan
├── Profil Perusahaan
├── Nomor Invoice
├── Pajak
├── Pengguna
├── Hak Akses
└── Audit Log
```

---

# 6. Dashboard

Tampilkan kartu statistik:

- Total invoice.
- Invoice hari ini.
- Invoice bulan berjalan.
- Total penjualan.
- Total pembayaran.
- Total piutang.
- Invoice belum dibayar.
- Invoice dibayar sebagian.
- Invoice lunas.
- Invoice jatuh tempo.
- Total barang masuk.
- Total modal.
- Estimasi laba kotor.

Tampilkan grafik:

- Penjualan harian.
- Penjualan bulanan.
- Pembayaran.
- Piutang.
- Modal dibanding omzet.
- Laba kotor.

Tampilkan invoice terbaru:

| Nomor | Pelanggan | Tanggal | Grand Total | Terbayar | Status |
|---|---|---|---:|---:|---|

---

# 7. Master Pelanggan

Data pelanggan:

- Kode pelanggan.
- Nama pelanggan.
- Nama perusahaan.
- Nomor telepon.
- WhatsApp.
- Email.
- NPWP.
- Alamat.
- Kota.
- Provinsi.
- Kode pos.
- Catatan.
- Status aktif.

Fitur:

- Tambah pelanggan.
- Edit pelanggan.
- Lihat detail.
- Cari pelanggan.
- Filter.
- Nonaktifkan pelanggan.
- Lihat riwayat invoice pelanggan.

---

# 8. Master Supplier

Data supplier:

- Kode supplier.
- Nama supplier.
- Nama perusahaan.
- Telepon.
- WhatsApp.
- Email.
- NPWP.
- Alamat.
- Kota.
- Provinsi.
- Catatan.
- Status aktif.

Fitur:

- Tambah.
- Edit.
- Detail.
- Pencarian.
- Riwayat barang masuk.

---

# 9. Master Data Barang

Data barang:

- SKU atau kode barang.
- Barcode opsional.
- Nama barang.
- Kategori.
- Satuan.
- Harga beli terakhir.
- Harga beli rata-rata.
- Harga jual default.
- Stok.
- Minimum stok.
- Deskripsi.
- Status aktif.

Contoh satuan:

- Pcs.
- Unit.
- Kg.
- Gram.
- Liter.
- Meter.
- Dus.
- Paket.
- Karung.
- Ton.

Harga beli hanya untuk kebutuhan internal dan tidak boleh ditampilkan pada invoice pelanggan.

---

# 10. Modul Data Barang Masuk

Digunakan untuk mencatat:

- Pembelian barang.
- Barang datang.
- Penambahan stok.
- Harga beli terbaru.

## Form Barang Masuk

Informasi transaksi:

- Nomor barang masuk otomatis.
- Tanggal barang masuk.
- Supplier.
- Nomor faktur supplier.
- Nomor purchase order opsional.
- Catatan.
- Lampiran nota atau faktur.

Detail barang:

| Nama Barang | Harga Beli | Qty | Volume | Satuan | Total |
|---|---:|---:|---:|---|---:|

Sediakan metode perhitungan:

```text
Berdasarkan Qty

atau

Berdasarkan Qty x Volume
```

Formula berdasarkan Qty:

```text
Total Barang = Harga Beli x Qty
```

Formula berdasarkan Qty dan Volume:

```text
Total Barang = Harga Beli x Qty x Volume
```

Setelah transaksi difinalisasi:

- Stok otomatis bertambah.
- Harga beli terakhir diperbarui.
- Harga beli rata-rata diperbarui.
- Data transaksi dikunci.
- Dibuat catatan pergerakan stok.

Status:

- Draft.
- Final.
- Dibatalkan.

---

# 11. Modul Pembuatan Invoice

## Informasi Invoice

Form terdiri dari:

- Nomor invoice otomatis.
- Tanggal invoice.
- Tanggal jatuh tempo.
- Pilih pelanggan.
- Alamat penagihan.
- Nomor purchase order pelanggan.
- Nama sales atau pembuat invoice.
- Catatan invoice.
- Syarat dan ketentuan.

## Detail Barang

Kolom:

| Nama Barang | Harga Beli | Harga Jual | Qty | Volume | Satuan | Total Harga |
|---|---:|---:|---:|---:|---|---:|

Ketentuan:

- Harga beli hanya terlihat oleh Super Admin, Admin, Finance, dan Pimpinan.
- Harga beli tidak tampil pada invoice cetak.
- Harga jual otomatis diambil dari master barang.
- Harga jual dapat diubah apabila pengguna memiliki izin.

Metode perhitungan:

### Berdasarkan Qty

```text
Total Item = Harga Jual x Qty
```

Contoh:

```text
Harga Jual = Rp100.000
Qty = 5

Total = Rp500.000
```

### Berdasarkan Qty dan Volume

```text
Total Item = Harga Jual x Qty x Volume
```

Contoh:

```text
Harga per Kg = Rp25.000
Qty = 4
Volume = 10 Kg

Total = Rp25.000 x 4 x 10
Total = Rp1.000.000
```

---

# 12. Perhitungan Invoice

Urutan perhitungan:

```text
Subtotal

Diskon

Dasar Pengenaan Pajak

Pajak

Biaya Kirim

Grand Total
```

## Subtotal

```text
Subtotal = Jumlah seluruh Total Item
```

## Diskon

Diskon memiliki dua tipe:

```text
Persentase

atau

Nominal Rupiah
```

Jika diskon persentase:

```text
Jumlah Diskon = Subtotal x Persentase Diskon / 100
```

Jika diskon Rupiah:

```text
Jumlah Diskon = Nominal Diskon
```

## Dasar Pajak

```text
Dasar Pajak = Subtotal - Diskon
```

## Pajak

```text
Jumlah Pajak = Dasar Pajak x Persentase Pajak / 100
```

## Grand Total

```text
Grand Total =
Subtotal
- Diskon
+ Pajak
+ Biaya Kirim
```

Contoh:

```text
Subtotal              Rp10.000.000
Diskon 10%            Rp 1.000.000
Pajak 11%             Rp   990.000
Biaya Kirim           Rp   300.000
---------------------------------
Grand Total           Rp10.290.000
```

---

# 13. Tampilan Ringkasan Invoice

Tampilkan pada bagian bawah invoice:

```text
Subtotal                    Rp10.000.000

Diskon 10%                  Rp 1.000.000

Dasar Pengenaan Pajak       Rp 9.000.000

Pajak 11%                   Rp   990.000

Biaya Kirim                 Rp   300.000

----------------------------------------

GRAND TOTAL                 Rp10.290.000
```

---

# 14. Status Invoice

Gunakan status:

- Draft.
- Diterbitkan.
- Belum Dibayar.
- Dibayar Sebagian.
- Lunas.
- Jatuh Tempo.
- Dibatalkan.

Warna:

- Draft: Abu-abu.
- Diterbitkan: Biru.
- Belum Dibayar: Oranye.
- Dibayar Sebagian: Kuning.
- Lunas: Hijau.
- Jatuh Tempo: Merah.
- Dibatalkan: Merah tua.

---

# 15. Halaman Semua Invoice

Tabel:

| Nomor Invoice | Tanggal | Pelanggan | Subtotal | Grand Total | Terbayar | Sisa | Status | Aksi |
|---|---|---|---:|---:|---:|---:|---|---|

Fitur:

- Pencarian nomor invoice.
- Pencarian nama pelanggan.
- Filter tanggal.
- Filter bulan.
- Filter tahun.
- Filter status.
- Filter pembuat invoice.
- Urutkan berdasarkan nilai.
- Pagination server-side.

Aksi:

- Lihat.
- Edit.
- Duplikasi.
- Cetak.
- Download PDF.
- Catat pembayaran.
- Kirim email.
- Batalkan.
- Hapus draft.

Ketentuan:

- Invoice yang sudah diterbitkan tidak boleh dihapus permanen.
- Invoice yang sudah diterbitkan hanya dapat dibatalkan.
- Invoice draft dapat dihapus.

---

# 16. Modul Pembayaran

Data pembayaran:

- Nomor pembayaran.
- Nomor invoice.
- Tanggal pembayaran.
- Nominal pembayaran.
- Metode pembayaran.
- Bank tujuan.
- Nomor referensi.
- Bukti pembayaran.
- Catatan.

Metode pembayaran:

- Transfer bank.
- Tunai.
- Kartu.
- QRIS.
- Virtual account.
- Lainnya.

Formula:

```text
Total Terbayar = Jumlah seluruh pembayaran invoice
```

```text
Sisa Tagihan = Grand Total - Total Terbayar
```

Status otomatis:

```text
Jika Total Terbayar = 0

Status = Belum Dibayar
```

```text
Jika Total Terbayar > 0
dan
Total Terbayar < Grand Total

Status = Dibayar Sebagian
```

```text
Jika Total Terbayar >= Grand Total

Status = Lunas
```

---

# 17. Perhitungan Modal dan Laba

Harga beli hanya digunakan secara internal.

Modal berdasarkan Qty:

```text
Modal Item = Harga Beli x Qty
```

Modal berdasarkan Qty dan Volume:

```text
Modal Item = Harga Beli x Qty x Volume
```

Total modal:

```text
Total Modal = Jumlah seluruh Modal Item
```

Laba kotor:

```text
Laba Kotor =
Subtotal setelah diskon
- Total Modal
```

Pajak tidak dihitung sebagai keuntungan.

Biaya kirim memiliki pengaturan:

- Pendapatan perusahaan.
- Penggantian biaya kirim.
- Tidak dihitung sebagai keuntungan.

---

# 18. Cetak Invoice

Invoice tersedia dalam:

- Preview.
- Print browser.
- PDF A4.
- Download PDF.

## Header Invoice

Tampilkan:

- Logo perusahaan.
- Nama perusahaan.
- Alamat.
- Nomor telepon.
- Email.
- NPWP.

## Informasi Invoice

Tampilkan:

- Nomor invoice.
- Tanggal invoice.
- Tanggal jatuh tempo.
- Status pembayaran.

## Informasi Pelanggan

Tampilkan:

- Nama pelanggan.
- Nama perusahaan.
- Alamat.
- Telepon.
- Email.
- NPWP.

## Detail Barang

| No | Nama Barang | Qty | Volume | Satuan | Harga | Total |
|---:|---|---:|---:|---|---:|---:|

Harga beli tidak boleh tampil.

## Ringkasan

```text
Subtotal

Diskon

Pajak

Biaya Kirim

Grand Total
```

## Footer

Tampilkan:

- Informasi rekening pembayaran.
- Syarat pembayaran.
- Catatan.
- Nama pembuat invoice.
- Kolom tanda tangan.
- QR Code verifikasi invoice.

---

# 19. Nomor Invoice Otomatis

Format default:

```text
INV/2026/07/00001
```

Format dapat dikonfigurasi:

```text
{PREFIX}/{YEAR}/{MONTH}/{SEQUENCE}
```

Contoh:

```text
INV/2026/07/00001
INV/2026/07/00002
INV/2026/07/00003
```

Ketentuan:

- Nomor invoice unik.
- Nomor tidak boleh digunakan ulang.
- Aman ketika banyak pengguna membuat invoice bersamaan.
- Gunakan database transaction.
- Gunakan row locking.

---

# 20. Struktur Database

Semua tabel menggunakan:

```sql
ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci
```

## Tabel users

```text
id
name
email
password
role
is_active
last_login_at
created_at
updated_at
```

## Tabel company_settings

```text
id
company_name
logo
address
city
province
postal_code
phone
whatsapp
email
website
tax_number
bank_name
bank_account_number
bank_account_name
invoice_footer
created_at
updated_at
```

## Tabel customers

```text
id
customer_code
name
company_name
phone
whatsapp
email
tax_number
address
city
province
postal_code
notes
is_active
created_at
updated_at
deleted_at
```

## Tabel suppliers

```text
id
supplier_code
name
company_name
phone
whatsapp
email
tax_number
address
city
province
notes
is_active
created_at
updated_at
deleted_at
```

## Tabel product_categories

```text
id
name
description
is_active
created_at
updated_at
```

## Tabel products

```text
id
category_id
sku
barcode
name
description
unit
purchase_price
average_purchase_price
selling_price
stock
minimum_stock
is_active
created_at
updated_at
deleted_at
```

## Tabel incoming_transactions

```text
id
transaction_number
supplier_id
transaction_date
supplier_invoice_number
purchase_order_number
subtotal
notes
attachment
status
finalized_at
created_by
created_at
updated_at
```

## Tabel incoming_transaction_items

```text
id
incoming_transaction_id
product_id
product_name_snapshot
purchase_price
quantity
volume
unit
calculation_method
line_total
created_at
updated_at
```

## Tabel invoices

```text
id
invoice_number
customer_id
invoice_date
due_date
purchase_order_number
billing_name
billing_company
billing_phone
billing_email
billing_address
subtotal
discount_type
discount_value
discount_amount
tax_percentage
tax_amount
shipping_cost
grand_total
total_cost
gross_profit
paid_amount
remaining_amount
status
notes
terms
issued_at
cancelled_at
created_by
created_at
updated_at
```

## Tabel invoice_items

```text
id
invoice_id
product_id
product_name_snapshot
sku_snapshot
unit_snapshot
purchase_price
selling_price
quantity
volume
calculation_method
line_subtotal
cost_total
profit
created_at
updated_at
```

## Tabel payments

```text
id
payment_number
invoice_id
payment_date
amount
payment_method
bank_name
reference_number
payment_proof
notes
created_by
created_at
updated_at
```

## Tabel stock_movements

```text
id
product_id
reference_type
reference_id
movement_type
quantity
stock_before
stock_after
notes
created_by
created_at
```

Jenis movement:

```text
IN
OUT
ADJUSTMENT
RETURN
```

## Tabel invoice_sequences

```text
id
year
month
last_number
created_at
updated_at
```

## Tabel activity_logs

```text
id
user_id
action
module
reference_type
reference_id
old_data
new_data
ip_address
user_agent
created_at
```

---

# 21. Tipe Data

Gunakan:

```text
DECIMAL(20,2)
```

Untuk:

- Harga beli.
- Harga jual.
- Subtotal.
- Pajak.
- Diskon.
- Biaya kirim.
- Grand total.
- Pembayaran.
- Keuntungan.

Gunakan:

```text
DECIMAL(15,4)
```

Untuk:

- Qty.
- Volume.
- Stok.

Jangan gunakan:

```text
FLOAT
DOUBLE
```

untuk nilai uang.

Gunakan database transaction pada:

- Finalisasi barang masuk.
- Pembuatan nomor invoice.
- Penerbitan invoice.
- Pembatalan invoice.
- Pencatatan pembayaran.
- Perubahan stok.

---

# 22. Audit Log

Catat aktivitas:

- Login.
- Logout.
- Membuat invoice.
- Mengubah invoice.
- Menerbitkan invoice.
- Membatalkan invoice.
- Mencetak invoice.
- Mengunduh PDF.
- Membuat pembayaran.
- Mengubah pembayaran.
- Membuat barang masuk.
- Finalisasi barang masuk.
- Mengubah harga barang.

Simpan:

- Pengguna.
- Waktu.
- IP address.
- Perangkat.
- Data sebelum.
- Data sesudah.

---

# 23. Laporan

## Laporan Invoice

Filter:

- Harian.
- Mingguan.
- Bulanan.
- Tahunan.
- Pelanggan.
- Status.

## Laporan Penjualan

Tampilkan:

- Total invoice.
- Total transaksi.
- Total penjualan.
- Total diskon.
- Total pajak.
- Total biaya kirim.
- Grand total.

## Laporan Modal

Tampilkan:

- Harga beli.
- Jumlah terjual.
- Total modal.

Hanya dapat dilihat oleh:

- Super Admin.
- Finance.
- Pimpinan.

## Laporan Keuntungan

Tampilkan:

```text
Omzet

Modal

Laba Kotor

Margin Keuntungan
```

Formula:

```text
Margin =
Laba Kotor / Pendapatan x 100
```

## Laporan Piutang

Tampilkan:

- Pelanggan.
- Nomor invoice.
- Tanggal jatuh tempo.
- Grand total.
- Terbayar.
- Sisa tagihan.
- Jumlah hari terlambat.

---

# 24. Export

Semua laporan dapat:

- Dicetak.
- Download PDF.
- Export Excel.
- Export CSV.

---

# 25. Validasi

Invoice tidak dapat diterbitkan apabila:

- Pelanggan belum dipilih.
- Tidak ada item.
- Qty nol.
- Harga jual nol.
- Grand total negatif.
- Nomor invoice sudah digunakan.

Pembayaran tidak dapat disimpan apabila:

- Nominal nol.
- Nominal negatif.
- Invoice dibatalkan.
- Nominal lebih besar dari sisa tagihan, kecuali fitur kelebihan pembayaran diaktifkan.

---

# 26. Desain UI

Gunakan desain:

- Modern.
- Profesional.
- Responsif.
- Ringan.
- Mobile friendly.

Warna utama:

```text
Primary:
#0EA5E9
```

Warna tambahan:

```text
Biru Gelap:
#0369A1

Background:
#F8FAFC

Hijau:
#16A34A

Kuning:
#EAB308

Merah:
#DC2626
```

Gunakan:

- Sidebar collapsible.
- Header.
- Breadcrumb.
- Card statistik.
- Data table.
- Search realtime.
- Filter.
- Pagination server-side.
- Modal konfirmasi.
- Toast notification.
- Loading skeleton.
- Dark mode opsional.

---

# 27. Struktur Folder Vue

```text
resources/js/

├── Components/
│   ├── DataTable/
│   ├── Invoice/
│   ├── Form/
│   ├── Modal/
│   ├── Dashboard/
│   └── UI/

├── Layouts/
│   ├── AuthenticatedLayout.vue
│   ├── GuestLayout.vue
│   └── PrintLayout.vue

├── Pages/
│   ├── Dashboard/
│   ├── Customers/
│   ├── Suppliers/
│   ├── Products/
│   ├── IncomingTransactions/
│   ├── Invoices/
│   ├── Payments/
│   ├── Reports/
│   └── Settings/

├── Types/
│   ├── customer.ts
│   ├── product.ts
│   ├── invoice.ts
│   ├── payment.ts
│   └── report.ts

└── app.ts
```

---

# 28. Ketentuan Pengembangan

Gunakan:

- Service Pattern untuk business logic.
- Form Request untuk validasi.
- Policy untuk hak akses.
- Database Transaction.
- TypeScript strict mode.
- Vue Composition API.
- Reusable component.
- Server-side pagination.
- Eager loading untuk mencegah N+1 query.
- Database index.
- Soft delete untuk master data.
- Audit trail.

Jangan:

- Menaruh seluruh logika di Controller.
- Menggunakan tipe `any` tanpa alasan.
- Menggunakan query database langsung di Vue.
- Menghitung nilai keuangan hanya dari frontend.
- Mempercayai hasil kalkulasi dari browser.

Semua perhitungan invoice harus dihitung ulang oleh Laravel.

---

# 29. Prioritas Pengerjaan

## Tahap 1

- Login.
- Pengguna.
- Profil perusahaan.
- Pelanggan.
- Supplier.
- Barang.
- Kategori.

## Tahap 2

- Data barang masuk.
- Stok.
- Pergerakan stok.

## Tahap 3

- Pembuatan invoice.
- Perhitungan otomatis.
- Semua invoice.
- Preview invoice.
- Cetak invoice.
- Download PDF.

## Tahap 4

- Pembayaran.
- Piutang.
- Status pembayaran.

## Tahap 5

- Dashboard.
- Laporan.
- Export Excel.
- Export PDF.

## Tahap 6

- Redis.
- Queue.
- Cache.
- Audit log.
- Optimasi.
- Pengujian.

---

# 30. Acceptance Criteria

Sistem dianggap selesai apabila dapat:

1. Login menggunakan role.
2. Mengelola pengguna.
3. Mengelola profil perusahaan.
4. Mengelola pelanggan.
5. Mengelola supplier.
6. Mengelola barang.
7. Menyimpan harga beli.
8. Menyimpan harga jual.
9. Mencatat barang masuk.
10. Menambah stok otomatis.
11. Membuat invoice.
12. Menghitung subtotal otomatis.
13. Menghitung diskon persen.
14. Menghitung diskon Rupiah.
15. Menghitung pajak.
16. Menghitung biaya kirim.
17. Menghitung grand total.
18. Mencetak invoice.
19. Download invoice PDF.
20. Menyimpan seluruh invoice.
21. Mencatat pembayaran.
22. Menghitung sisa tagihan.
23. Memperbarui status invoice otomatis.
24. Menghitung modal.
25. Menghitung laba kotor.
26. Menampilkan dashboard.
27. Membuat laporan.
28. Export Excel.
29. Export PDF.
30. Menyimpan audit aktivitas.

---

# 31. Instruksi Khusus untuk Codex

Kerjakan aplikasi secara bertahap.

Urutan pengerjaan:

1. Buat project Laravel dengan Vue 3, Inertia.js, TypeScript, Tailwind CSS.
2. Konfigurasi MySQL InnoDB.
3. Konfigurasi Redis.
4. Buat authentication.
5. Buat role dan permission.
6. Buat migration.
7. Buat model.
8. Buat factory dan seeder.
9. Buat service.
10. Buat Form Request.
11. Buat Policy.
12. Buat Controller.
13. Buat halaman Vue.
14. Buat komponen reusable.
15. Buat modul barang masuk.
16. Buat modul invoice.
17. Buat modul pembayaran.
18. Buat cetak invoice PDF.
19. Buat dashboard.
20. Buat laporan.
21. Buat export.
22. Buat audit log.
23. Buat automated test.
24. Optimasi query.
25. Dokumentasikan instalasi.

Gunakan clean code.

Jangan membuat seluruh fitur dalam satu file.

Pisahkan business logic ke service.

Gunakan TypeScript strict mode.

Gunakan komponen reusable.

Pastikan seluruh transaksi keuangan menggunakan database transaction.

Pastikan seluruh nilai uang menggunakan DECIMAL.

Pastikan harga beli tidak tampil pada invoice pelanggan.

Pastikan invoice yang sudah diterbitkan tidak dapat dihapus permanen.

Pastikan semua hasil perhitungan frontend divalidasi ulang oleh backend.

Buat README instalasi dan deployment.
