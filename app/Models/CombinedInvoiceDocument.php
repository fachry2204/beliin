<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CombinedInvoiceDocument extends Model
{
    protected $fillable = ['facture_number', 'customer_id', 'status', 'opened_at', 'due_date', 'closed_at'];

    protected function casts(): array
    {
        return ['opened_at' => 'datetime', 'due_date' => 'date:Y-m-d', 'closed_at' => 'datetime'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoices()
    {
        return $this->belongsToMany(Invoice::class)->withTimestamps();
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
