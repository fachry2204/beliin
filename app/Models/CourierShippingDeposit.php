<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourierShippingDeposit extends Model
{
    protected $fillable = [
        'invoice_id',
        'courier_id',
        'amount',
        'paid_at',
        'cash_transaction_id',
        'created_by',
        'paid_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function courier()
    {
        return $this->belongsTo(Courier::class);
    }

    public function cashTransaction()
    {
        return $this->belongsTo(CashTransaction::class);
    }
}
