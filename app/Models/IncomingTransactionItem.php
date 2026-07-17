<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncomingTransactionItem extends Model
{
    protected $fillable = ['product_id', 'product_name_snapshot', 'purchase_price', 'quantity', 'volume', 'unit', 'calculation_method', 'line_total'];

    public function transaction()
    {
        return $this->belongsTo(IncomingTransaction::class, 'incoming_transaction_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
