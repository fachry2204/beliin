<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('payments')
            ->whereNotNull('combined_invoice_document_id')
            ->orderBy('id')
            ->get(['id', 'invoice_id', 'combined_invoice_document_id'])
            ->each(function ($payment) {
                $facture = DB::table('combined_invoice_documents')
                    ->where('id', $payment->combined_invoice_document_id)
                    ->value('facture_number');
                $invoice = DB::table('invoices')
                    ->where('id', $payment->invoice_id)
                    ->first(['invoice_number', 'billing_name']);

                if (! $facture || ! $invoice) {
                    return;
                }

                DB::table('cash_transactions')
                    ->where('payment_id', $payment->id)
                    ->update([
                        'description' => "Pembayaran Faktur {$facture} | Invoice {$invoice->invoice_number} - {$invoice->billing_name}",
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        DB::table('payments')
            ->whereNotNull('combined_invoice_document_id')
            ->orderBy('id')
            ->get(['id', 'invoice_id'])
            ->each(function ($payment) {
                $invoice = DB::table('invoices')
                    ->where('id', $payment->invoice_id)
                    ->first(['invoice_number', 'billing_name']);

                if (! $invoice) {
                    return;
                }

                DB::table('cash_transactions')
                    ->where('payment_id', $payment->id)
                    ->update([
                        'description' => "Pembayaran {$invoice->invoice_number} - {$invoice->billing_name}",
                        'updated_at' => now(),
                    ]);
            });
    }
};
