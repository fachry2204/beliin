<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PdfService
{
    public function generateInvoice(Invoice $invoice): string
    {
        $invoice->load(['customer', 'items', 'creator:id,name']);
        $path = 'invoices/'.str_replace('/', '-', $invoice->invoice_number).'.pdf';
        Storage::disk('local')->put($path, Pdf::loadView('invoices.print', ['invoice' => $invoice, 'company' => CompanySetting::first()])->setPaper('a4')->output());

        return $path;
    }
}
