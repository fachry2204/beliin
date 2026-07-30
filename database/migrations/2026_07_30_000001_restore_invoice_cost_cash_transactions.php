<?php

use App\Enums\InvoiceStatus;
use App\Models\CashTransaction;
use App\Models\Invoice;
use App\Services\CashTransactionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cash_transactions', 'invoice_cost_id')) {
            return;
        }

        Invoice::query()
            ->whereNotNull('issued_at')
            ->whereNotIn('status', [
                InvoiceStatus::Draft->value,
                InvoiceStatus::Cancelled->value,
            ])
            ->chunkById(100, function ($invoices): void {
                foreach ($invoices as $invoice) {
                    app(CashTransactionService::class)
                        ->syncInvoiceCost($invoice, (int) $invoice->created_by);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('cash_transactions', 'invoice_cost_id')) {
            return;
        }

        CashTransaction::withTrashed()
            ->whereNotNull('invoice_cost_id')
            ->forceDelete();
    }
};
