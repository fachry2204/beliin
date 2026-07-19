<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanySetting extends Model
{
    protected $fillable = ['company_name', 'logo', 'favicon', 'address', 'city', 'province', 'postal_code', 'phone', 'whatsapp', 'email', 'website', 'tax_number', 'bank_name', 'bank_account_number', 'bank_account_name', 'invoice_footer', 'invoice_prefix', 'default_tax_percentage', 'tax_enabled', 'discount_enabled', 'commission_margin_warning_percentage', 'shipping_is_revenue', 'printer_type', 'printer_paper_size', 'printer_orientation', 'backup_auto_enabled', 'backup_auto_type', 'backup_auto_frequency', 'backup_auto_time', 'backup_retention_count', 'backup_last_run_at', 'backup_last_error'];

    protected function casts(): array
    {
        return ['default_tax_percentage' => 'integer', 'tax_enabled' => 'boolean', 'discount_enabled' => 'boolean', 'commission_margin_warning_percentage' => 'integer', 'shipping_is_revenue' => 'boolean', 'backup_auto_enabled' => 'boolean', 'backup_retention_count' => 'integer', 'backup_last_run_at' => 'datetime'];
    }
}
