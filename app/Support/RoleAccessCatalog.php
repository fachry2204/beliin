<?php

namespace App\Support;

class RoleAccessCatalog
{
    public static function groups(): array
    {
        return [
            ['label' => 'Dashboard', 'items' => [
                ['name' => 'dashboard.view', 'label' => 'Dashboard', 'description' => 'Melihat ringkasan dashboard.'],
            ]],
            ['label' => 'Master Data', 'items' => [
                ['name' => 'customers.view', 'label' => 'Lihat Pelanggan', 'description' => 'Membuka halaman pelanggan.'],
                ['name' => 'customers.manage', 'label' => 'Kelola Pelanggan', 'description' => 'Menambah, mengubah, dan menghapus pelanggan.'],
                ['name' => 'products.view', 'label' => 'Lihat Barang & Kategori', 'description' => 'Membuka data barang dan kategori.'],
                ['name' => 'products.manage', 'label' => 'Kelola Barang & Kategori', 'description' => 'Menambah, mengubah, dan menghapus barang atau kategori.'],
            ]],
            ['label' => 'Kurir', 'items' => [
                ['name' => 'couriers.view', 'label' => 'Lihat Data Kurir', 'description' => 'Membuka daftar dan detail kurir.'],
                ['name' => 'couriers.manage', 'label' => 'Kelola Kurir', 'description' => 'Menambah dan memperbarui data kurir.'],
                ['name' => 'couriers.map', 'label' => 'Map Kurir', 'description' => 'Melihat lokasi kurir pada peta.'],
            ]],
            ['label' => 'Transaksi', 'items' => [
                ['name' => 'invoices.view', 'label' => 'Lihat Invoice & Faktur', 'description' => 'Membuka daftar dan detail invoice atau faktur.'],
                ['name' => 'invoices.create', 'label' => 'Buat & Edit', 'description' => 'Membuat dan mengubah invoice atau faktur.'],
                ['name' => 'invoices.issue', 'label' => 'Terbitkan Invoice', 'description' => 'Menerbitkan invoice dan menentukan pengiriman.'],
                ['name' => 'invoices.cancel', 'label' => 'Batalkan Invoice', 'description' => 'Membatalkan invoice.'],
                ['name' => 'invoices.delete', 'label' => 'Hapus Invoice & Faktur', 'description' => 'Menghapus data sesuai aturan status.'],
                ['name' => 'invoices.print', 'label' => 'Cetak & PDF', 'description' => 'Mencetak atau mengunduh dokumen.'],
                ['name' => 'profit.view', 'label' => 'Margin & Komisi Faktur', 'description' => 'Melihat margin dan halaman komisi faktur.'],
                ['name' => 'payments.view', 'label' => 'Lihat Pembayaran & Piutang', 'description' => 'Membuka halaman pembayaran dan piutang.'],
                ['name' => 'payments.manage', 'label' => 'Kelola Pembayaran', 'description' => 'Mencatat atau mengubah pembayaran dan komisi.'],
                ['name' => 'cash.view', 'label' => 'Lihat Kas', 'description' => 'Membuka Cash Masuk dan Cash Keluar.'],
                ['name' => 'cash.manage', 'label' => 'Kelola Kas', 'description' => 'Menambah, mengubah, dan menghapus transaksi kas manual.'],
            ]],
            ['label' => 'Laporan', 'items' => [
                ['name' => 'reports.view', 'label' => 'Lihat Laporan', 'description' => 'Membuka seluruh halaman laporan.'],
                ['name' => 'reports.export', 'label' => 'Ekspor Laporan', 'description' => 'Mengunduh laporan CSV, Excel, atau PDF.'],
            ]],
            ['label' => 'Pengaturan', 'items' => [
                ['name' => 'settings.manage', 'label' => 'Pengaturan Perusahaan & Role', 'description' => 'Mengubah profil perusahaan dan akses role.'],
                ['name' => 'users.manage', 'label' => 'Kelola Pengguna', 'description' => 'Membuka dan mengelola akun pengguna.'],
                ['name' => 'audit.view', 'label' => 'Audit Log', 'description' => 'Melihat riwayat aktivitas sistem.'],
            ]],
            ['label' => 'Portal Kurir', 'items' => [
                ['name' => 'courier.portal', 'label' => 'Akses Portal Kurir', 'description' => 'Melihat tugas, ongkir, lokasi, dan profil kurir.'],
            ]],
        ];
    }

    public static function names(): array
    {
        return collect(self::groups())->flatMap(fn (array $group) => $group['items'])->pluck('name')->all();
    }

    public static function withRequiredParents(array $permissions): array
    {
        $dependencies = [
            'customers.manage' => 'customers.view',
            'products.manage' => 'products.view',
            'couriers.manage' => 'couriers.view',
            'invoices.create' => 'invoices.view',
            'invoices.issue' => 'invoices.view',
            'invoices.cancel' => 'invoices.view',
            'invoices.delete' => 'invoices.view',
            'invoices.print' => 'invoices.view',
            'payments.manage' => 'payments.view',
            'cash.manage' => 'cash.view',
            'reports.export' => 'reports.view',
        ];

        foreach ($permissions as $permission) {
            if (isset($dependencies[$permission])) {
                $permissions[] = $dependencies[$permission];
            }
        }

        return array_values(array_unique($permissions));
    }
}
