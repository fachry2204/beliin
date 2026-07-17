<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facture_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('combined_invoice_document_id')->constrained()->restrictOnDelete();
            $table->date('facture_payment_date')->index();
            $table->string('commission_base', 30);
            $table->string('commission_type', 20);
            $table->decimal('commission_value', 20, 4);
            $table->decimal('base_amount', 20, 2);
            $table->decimal('facture_total', 20, 2);
            $table->decimal('margin_total', 20, 2);
            $table->decimal('commission_amount', 20, 2);
            $table->string('status', 20)->default('unpaid')->index();
            $table->text('notes')->nullable();
            $table->date('paid_date')->nullable();
            $table->string('payment_method', 30)->nullable();
            $table->text('payment_notes')->nullable();
            $table->foreignId('cash_transaction_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        DB::table('cash_transactions')
            ->where('type', 'out')
            ->where('category', 'Komisi Pembayaran Faktur')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->each(function ($cash) {
                $document = DB::table('combined_invoice_documents')
                    ->where('facture_number', $cash->reference_number)
                    ->first();
                if (! $document) {
                    return;
                }

                $totals = DB::table('combined_invoice_document_invoice as link')
                    ->join('invoices', 'invoices.id', '=', 'link.invoice_id')
                    ->where('link.combined_invoice_document_id', $document->id)
                    ->selectRaw('COALESCE(SUM(invoices.grand_total), 0) as facture_total, COALESCE(SUM(invoices.gross_profit), 0) as margin_total')
                    ->first();

                DB::table('facture_commissions')->insert([
                    'combined_invoice_document_id' => $document->id,
                    'facture_payment_date' => $cash->transaction_date,
                    'commission_base' => 'facture_total',
                    'commission_type' => 'nominal',
                    'commission_value' => $cash->amount,
                    'base_amount' => $totals->facture_total,
                    'facture_total' => $totals->facture_total,
                    'margin_total' => $totals->margin_total,
                    'commission_amount' => $cash->amount,
                    'status' => 'paid',
                    'notes' => $cash->notes,
                    'paid_date' => $cash->transaction_date,
                    'payment_method' => $cash->payment_method,
                    'payment_notes' => $cash->notes,
                    'cash_transaction_id' => $cash->id,
                    'created_by' => $cash->created_by,
                    'paid_by' => $cash->created_by,
                    'paid_at' => $cash->created_at,
                    'created_at' => $cash->created_at,
                    'updated_at' => $cash->updated_at,
                ]);

                DB::table('cash_transactions')->where('id', $cash->id)->update([
                    'category' => 'Komisi Faktur',
                    'description' => 'Pembayaran Komisi Faktur '.$document->facture_number,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('facture_commissions');
    }
};
