<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            ['CUS-DEMO-001', 'Andi Pratama', 'Toko Berkah Jaya', '081210001001', 'andi@example.com', 'Jakarta'],
            ['CUS-DEMO-002', 'Siti Rahma', 'Warung Siti', '081210001002', 'siti@example.com', 'Bogor'],
            ['CUS-DEMO-003', 'Budi Santoso', 'Sembako Makmur', '081210001003', 'budi@example.com', 'Depok'],
            ['CUS-DEMO-004', 'Rina Wulandari', 'Kios Rina', '081210001004', 'rina@example.com', 'Tangerang'],
            ['CUS-DEMO-005', 'Dedi Kurniawan', 'UD Maju Lancar', '081210001005', 'dedi@example.com', 'Bekasi'],
            ['CUS-DEMO-006', 'Maya Lestari', 'Toko Serba Ada', '081210001006', 'maya@example.com', 'Bandung'],
            ['CUS-DEMO-007', 'Agus Salim', 'Warung Nusantara', '081210001007', 'agus@example.com', 'Cirebon'],
            ['CUS-DEMO-008', 'Nur Aisyah', 'Kedai Aisyah', '081210001008', 'aisyah@example.com', 'Karawang'],
            ['CUS-DEMO-009', 'Hendra Gunawan', 'CV Pangan Sejahtera', '081210001009', 'hendra@example.com', 'Serang'],
            ['CUS-DEMO-010', 'Dewi Anggraini', 'Toko Dewi', '081210001010', 'dewi@example.com', 'Sukabumi'],
        ];

        foreach ($customers as [$code, $name, $company, $phone, $email, $city]) {
            Customer::updateOrCreate(['customer_code' => $code], [
                'name' => $name,
                'company_name' => $company,
                'phone' => $phone,
                'whatsapp' => $phone,
                'email' => $email,
                'address' => "Jl. {$city} Raya No. ".substr($code, -2),
                'city' => $city,
                'province' => 'Jawa Barat',
                'is_active' => true,
            ]);
        }

        $sayuran = ProductCategory::updateOrCreate(['name' => 'Sayuran'], ['description' => 'Aneka sayuran segar', 'is_active' => true]);
        $sembako = ProductCategory::updateOrCreate(['name' => 'Sembako'], ['description' => 'Kebutuhan pokok sehari-hari', 'is_active' => true]);

        $products = [
            [$sayuran->id, 'SYR-001', 'Bayam Segar', 'Ikat', 3000, 5000],
            [$sayuran->id, 'SYR-002', 'Kangkung Segar', 'Ikat', 3000, 5000],
            [$sayuran->id, 'SYR-003', 'Sawi Hijau', 'Kg', 9000, 13000],
            [$sayuran->id, 'SYR-004', 'Wortel', 'Kg', 12000, 17000],
            [$sayuran->id, 'SYR-005', 'Kentang', 'Kg', 14000, 19000],
            [$sayuran->id, 'SYR-006', 'Tomat Merah', 'Kg', 10000, 15000],
            [$sayuran->id, 'SYR-007', 'Cabai Merah Keriting', 'Kg', 45000, 55000],
            [$sayuran->id, 'SYR-008', 'Bawang Merah', 'Kg', 32000, 40000],
            [$sayuran->id, 'SYR-009', 'Bawang Putih', 'Kg', 30000, 38000],
            [$sayuran->id, 'SYR-010', 'Kol Putih', 'Kg', 8000, 12000],
            [$sembako->id, 'SMB-001', 'Beras Premium 5 Kg', 'Karung', 65000, 75000],
            [$sembako->id, 'SMB-002', 'Gula Pasir 1 Kg', 'Pcs', 16000, 18000],
            [$sembako->id, 'SMB-003', 'Minyak Goreng 1 Liter', 'Pcs', 17000, 20000],
            [$sembako->id, 'SMB-004', 'Tepung Terigu 1 Kg', 'Pcs', 11000, 14000],
            [$sembako->id, 'SMB-005', 'Telur Ayam', 'Kg', 26000, 31000],
            [$sembako->id, 'SMB-006', 'Garam Halus 500 Gram', 'Pcs', 4000, 6000],
            [$sembako->id, 'SMB-007', 'Mi Instan Goreng', 'Pcs', 2800, 3500],
            [$sembako->id, 'SMB-008', 'Kecap Manis 600 ml', 'Botol', 18000, 23000],
            [$sembako->id, 'SMB-009', 'Susu Kental Manis', 'Kaleng', 11000, 14000],
            [$sembako->id, 'SMB-010', 'Teh Celup 25 Sachet', 'Kotak', 7000, 10000],
        ];

        foreach ($products as [$categoryId, $sku, $name, $unit, $purchasePrice, $sellingPrice]) {
            Product::updateOrCreate(['sku' => $sku], [
                'category_id' => $categoryId,
                'name' => $name,
                'unit' => $unit,
                'purchase_price' => $purchasePrice,
                'average_purchase_price' => $purchasePrice,
                'selling_price' => $sellingPrice,
                'stock' => 0,
                'minimum_stock' => 0,
                'is_active' => true,
            ]);
        }
    }
}
