<?php

namespace App\Models;

use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Model;

class IncomingTransaction extends Model
{
    protected $fillable = ['transaction_number', 'supplier_id', 'transaction_date', 'supplier_invoice_number', 'purchase_order_number', 'subtotal', 'notes', 'attachment', 'status', 'finalized_at', 'created_by'];

    protected function casts(): array
    {
        return ['transaction_date' => 'date', 'subtotal' => 'decimal:2', 'status' => TransactionStatus::class, 'finalized_at' => 'datetime'];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(IncomingTransactionItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
