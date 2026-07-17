<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['supplier_code', 'name', 'company_name', 'phone', 'whatsapp', 'email', 'tax_number', 'address', 'city', 'province', 'notes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function incomingTransactions()
    {
        return $this->hasMany(IncomingTransaction::class);
    }
}
