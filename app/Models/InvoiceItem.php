<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $fillable = ['product_id', 'product_name_snapshot', 'sku_snapshot', 'unit_snapshot', 'purchase_price', 'selling_price', 'quantity', 'volume', 'calculation_method', 'line_subtotal', 'cost_total', 'profit'];

    protected $hidden = ['purchase_price', 'cost_total', 'profit'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
