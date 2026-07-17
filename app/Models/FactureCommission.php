<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FactureCommission extends Model
{
    protected $fillable = [
        'combined_invoice_document_id',
        'facture_payment_date',
        'commission_base',
        'commission_type',
        'commission_value',
        'base_amount',
        'facture_total',
        'margin_total',
        'commission_amount',
        'status',
        'notes',
        'paid_date',
        'payment_method',
        'payment_notes',
        'cash_transaction_id',
        'created_by',
        'paid_by',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'facture_payment_date' => 'date:Y-m-d',
            'paid_date' => 'date:Y-m-d',
            'commission_value' => 'decimal:4',
            'base_amount' => 'decimal:2',
            'facture_total' => 'decimal:2',
            'margin_total' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function document()
    {
        return $this->belongsTo(CombinedInvoiceDocument::class, 'combined_invoice_document_id');
    }

    public function cashTransaction()
    {
        return $this->belongsTo(CashTransaction::class);
    }
}
