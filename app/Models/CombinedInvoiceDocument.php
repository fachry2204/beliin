<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CombinedInvoiceDocument extends Model
{
    protected $fillable = ['facture_number', 'customer_id', 'courier_id', 'courier_name', 'shipping_cost', 'status', 'opened_at', 'due_date', 'closed_at'];

    protected function casts(): array
    {
        return ['opened_at' => 'datetime', 'due_date' => 'date:Y-m-d', 'shipping_cost' => 'decimal:2', 'closed_at' => 'datetime'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoices()
    {
        return $this->belongsToMany(Invoice::class)->withTimestamps();
    }

    public function courier()
    {
        return $this->belongsTo(Courier::class);
    }

    public function shippingCashTransaction()
    {
        return $this->hasOne(CashTransaction::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function commissions()
    {
        return $this->hasMany(FactureCommission::class);
    }
}
