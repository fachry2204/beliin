<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\PdfService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateInvoicePdf implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $invoiceId) {}

    public function handle(PdfService $pdf): void
    {
        $pdf->generateInvoice(Invoice::findOrFail($this->invoiceId));
    }
}
