<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = ['dashboard.view', 'customers.view', 'customers.manage', 'couriers.view', 'couriers.manage', 'couriers.map', 'courier.portal', 'suppliers.view', 'suppliers.manage', 'products.view', 'products.manage', 'incoming.view', 'incoming.manage', 'invoices.view', 'invoices.create', 'invoices.issue', 'invoices.cancel', 'invoices.delete', 'invoices.print', 'payments.view', 'payments.manage', 'cash.view', 'cash.manage', 'reports.view', 'reports.export', 'profit.view', 'settings.manage', 'users.manage', 'audit.view'];
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $super = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $super->syncPermissions($permissions);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web'])->syncPermissions(array_diff($permissions, ['users.manage', 'settings.manage', 'courier.portal']));
        Role::firstOrCreate(['name' => 'Finance', 'guard_name' => 'web'])->syncPermissions(array_diff($permissions, ['users.manage', 'settings.manage', 'products.manage', 'courier.portal', 'couriers.map']));
        Role::firstOrCreate(['name' => 'Staff', 'guard_name' => 'web'])->syncPermissions(['dashboard.view', 'customers.view', 'customers.manage', 'couriers.view', 'couriers.manage', 'products.view', 'invoices.view', 'invoices.create', 'invoices.print', 'cash.view', 'cash.manage']);
        Role::firstOrCreate(['name' => 'Pimpinan', 'guard_name' => 'web'])->syncPermissions(['dashboard.view', 'invoices.view', 'invoices.print', 'cash.view', 'reports.view', 'reports.export', 'profit.view']);
        Role::firstOrCreate(['name' => 'Kurir', 'guard_name' => 'web'])->syncPermissions(['dashboard.view', 'courier.portal']);
    }
}
