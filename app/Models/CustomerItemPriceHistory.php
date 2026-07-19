<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerItemPriceHistory extends Model
{
    protected $fillable = [
        'customer_id',
        'invoice_id',
        'product_id',
        'line_number',
        'item_key',
        'product_name',
        'sku',
        'unit',
        'purchase_price',
        'selling_price',
        'invoice_date',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'invoice_date' => 'date',
            'recorded_at' => 'datetime',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
