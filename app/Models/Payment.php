<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['payment_number', 'invoice_id', 'combined_invoice_document_id', 'payment_date', 'amount', 'payment_method', 'bank_name', 'reference_number', 'payment_proof', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['payment_date' => 'date:Y-m-d', 'amount' => 'decimal:2'];
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function combinedInvoice()
    {
        return $this->belongsTo(CombinedInvoiceDocument::class, 'combined_invoice_document_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cashTransaction()
    {
        return $this->hasOne(CashTransaction::class);
    }
}
