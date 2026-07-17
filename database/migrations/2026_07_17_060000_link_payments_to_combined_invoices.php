<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('combined_invoice_document_id')
                ->nullable()
                ->after('invoice_id')
                ->constrained()
                ->restrictOnDelete();
        });

        DB::table('combined_invoice_documents')->orderBy('id')->each(function ($document) {
            DB::table('payments')
                ->whereNull('combined_invoice_document_id')
                ->where('notes', 'like', '%'.$document->facture_number.'%')
                ->update(['combined_invoice_document_id' => $document->id]);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('combined_invoice_document_id');
        });
    }
};
