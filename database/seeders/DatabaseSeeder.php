<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use App\Models\ProductCategory;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);
        $admin = User::firstOrCreate(['username' => env('SUPER_ADMIN_USERNAME', 'admin')], ['name' => 'Super Admin', 'email' => env('SUPER_ADMIN_EMAIL', 'admin@invoflow.test'), 'password' => env('SUPER_ADMIN_PASSWORD', 'ChangeMe123!'), 'email_verified_at' => now(), 'is_active' => true]);
        $admin->syncRoles(['Super Admin']);
        CompanySetting::firstOrCreate(['company_name' => 'InvoFlow Indonesia'], ['address' => 'Jakarta, Indonesia', 'email' => 'finance@invoflow.test', 'invoice_prefix' => 'INV', 'default_tax_percentage' => 11]);
        ProductCategory::firstOrCreate(['name' => 'Umum'], ['description' => 'Kategori barang umum', 'is_active' => true]);
    }
}
