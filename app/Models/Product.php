<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['category_id', 'sku', 'barcode', 'name', 'description', 'unit', 'purchase_price', 'average_purchase_price', 'selling_price', 'stock', 'minimum_stock', 'is_active'];

    protected function casts(): array
    {
        return ['purchase_price' => 'decimal:2', 'average_purchase_price' => 'decimal:2', 'selling_price' => 'decimal:2', 'stock' => 'decimal:4', 'minimum_stock' => 'decimal:4', 'is_active' => 'boolean'];
    }

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }
}
