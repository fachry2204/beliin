<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = ['invoice_number', 'customer_id', 'courier_id', 'courier_name', 'invoice_date', 'due_date', 'purchase_order_number', 'billing_name', 'billing_company', 'billing_phone', 'billing_email', 'billing_address', 'subtotal', 'discount_type', 'discount_value', 'discount_amount', 'tax_percentage', 'tax_amount', 'shipping_cost', 'grand_total', 'total_cost', 'gross_profit', 'paid_amount', 'remaining_amount', 'status', 'notes', 'terms', 'issued_at', 'cancelled_at', 'created_by'];

    protected function casts(): array
    {
        return ['invoice_date' => 'date', 'due_date' => 'date', 'subtotal' => 'decimal:2', 'discount_value' => 'decimal:2', 'discount_amount' => 'decimal:2', 'tax_percentage' => 'integer', 'tax_amount' => 'decimal:2', 'shipping_cost' => 'decimal:2', 'grand_total' => 'decimal:2', 'total_cost' => 'decimal:2', 'gross_profit' => 'decimal:2', 'paid_amount' => 'decimal:2', 'remaining_amount' => 'decimal:2', 'status' => InvoiceStatus::class, 'issued_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function courier()
    {
        return $this->belongsTo(Courier::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function customerItemPriceHistories()
    {
        return $this->hasMany(CustomerItemPriceHistory::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function combinedDocuments()
    {
        return $this->belongsToMany(CombinedInvoiceDocument::class)->withTimestamps();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function shippingDeposit()
    {
        return $this->hasOne(CourierShippingDeposit::class);
    }

    public function delivery()
    {
        return $this->hasOne(CourierDelivery::class);
    }
}
