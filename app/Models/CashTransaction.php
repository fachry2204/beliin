<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashTransaction extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'transaction_number',
        'payment_id',
        'invoice_id',
        'combined_invoice_document_id',
        'type',
        'transaction_date',
        'category',
        'description',
        'payment_method',
        'amount',
        'reference_number',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return ['transaction_date' => 'date:Y-m-d', 'amount' => 'decimal:2'];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function combinedInvoiceDocument()
    {
        return $this->belongsTo(CombinedInvoiceDocument::class);
    }

    public function factureCommission()
    {
        return $this->hasOne(FactureCommission::class);
    }
}
