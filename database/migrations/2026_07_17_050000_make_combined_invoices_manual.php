<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('combined_invoice_documents', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('opened_at');
        });

        Schema::create('combined_invoice_document_invoice', function (Blueprint $table) {
            $table->id();
            $table->foreignId('combined_invoice_document_id')
                ->constrained('combined_invoice_documents', 'id', 'ci_doc_inv_doc_fk')
                ->cascadeOnDelete();
            $table->foreignId('invoice_id')
                ->unique()
                ->constrained('invoices', 'id', 'ci_doc_inv_invoice_fk')
                ->cascadeOnDelete();
            $table->timestamps();
        });

        DB::table('combined_invoice_documents')->orderBy('id')->each(function ($document) {
            DB::table('combined_invoice_documents')->where('id', $document->id)->update([
                'due_date' => Carbon::parse($document->opened_at)->addWeek()->toDateString(),
            ]);

            $invoiceIds = DB::table('invoices')
                ->where('customer_id', $document->customer_id)
                ->whereIn('status', ['unpaid', 'partially_paid', 'overdue'])
                ->pluck('id');

            foreach ($invoiceIds as $invoiceId) {
                DB::table('combined_invoice_document_invoice')->insertOrIgnore([
                    'combined_invoice_document_id' => $document->id,
                    'invoice_id' => $invoiceId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combined_invoice_document_invoice');
        Schema::table('combined_invoice_documents', function (Blueprint $table) {
            $table->dropColumn('due_date');
        });
    }
};
