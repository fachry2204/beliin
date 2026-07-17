<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = ['product_id', 'reference_type', 'reference_id', 'movement_type', 'quantity', 'stock_before', 'stock_after', 'notes', 'created_by'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
