<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    public const DEFAULT_CASH_OUT_CATEGORIES = [
        'Operasional',
        'Pembelian Barang',
        'Transportasi',
        'Gaji & Upah',
        'Sewa',
        'Listrik & Internet',
        'Perawatan',
        'Pajak & Administrasi',
        'Lainnya',
    ];

    public const DEFAULT_CASH_IN_CATEGORIES = [
        'Penjualan Tunai',
        'Setoran Modal',
        'Pendapatan Jasa',
        'Pengembalian Dana',
        'Pendapatan Lainnya',
    ];

    protected $fillable = ['company_name', 'logo', 'favicon', 'address', 'city', 'province', 'postal_code', 'phone', 'whatsapp', 'email', 'website', 'tax_number', 'bank_name', 'bank_account_number', 'bank_account_name', 'invoice_footer', 'invoice_prefix', 'default_tax_percentage', 'tax_enabled', 'discount_enabled', 'commission_margin_warning_percentage', 'shipping_is_revenue', 'printer_type', 'printer_paper_size', 'printer_orientation', 'cash_in_categories', 'cash_out_categories', 'backup_auto_enabled', 'backup_auto_type', 'backup_auto_frequency', 'backup_auto_time', 'backup_retention_count', 'backup_last_run_at', 'backup_last_error'];

    protected function casts(): array
    {
        return ['default_tax_percentage' => 'integer', 'tax_enabled' => 'boolean', 'discount_enabled' => 'boolean', 'commission_margin_warning_percentage' => 'integer', 'shipping_is_revenue' => 'boolean', 'cash_in_categories' => 'array', 'cash_out_categories' => 'array', 'backup_auto_enabled' => 'boolean', 'backup_retention_count' => 'integer', 'backup_last_run_at' => 'datetime'];
    }

    public static function availableCashInCategories(): array
    {
        $categories = static::query()->first()?->cash_in_categories;

        return static::normalizeCashCategories($categories ?: self::DEFAULT_CASH_IN_CATEGORIES);
    }

    public static function availableCashOutCategories(): array
    {
        $categories = static::query()->first()?->cash_out_categories;

        return static::normalizeCashCategories($categories ?: self::DEFAULT_CASH_OUT_CATEGORIES);
    }

    private static function normalizeCashCategories(array $categories): array
    {
        return collect($categories)
            ->filter(fn ($category) => is_string($category) && trim($category) !== '')
            ->map(fn (string $category) => trim($category))
            ->unique(fn (string $category) => mb_strtolower($category))
            ->values()
            ->all();
    }
}
