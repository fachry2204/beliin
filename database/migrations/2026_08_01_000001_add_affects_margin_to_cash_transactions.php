<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->boolean('affects_margin')->default(false)->after('amount');
        });

        DB::table('cash_transactions')
            ->where('type', 'out')
            ->whereNull('payment_id')
            ->whereNull('invoice_id')
            ->whereNull('invoice_cost_id')
            ->whereNull('combined_invoice_document_id')
            ->whereNotIn('id', DB::table('facture_commissions')->whereNotNull('cash_transaction_id')->select('cash_transaction_id'))
            ->update(['affects_margin' => true]);
    }

    public function down(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->dropColumn('affects_margin');
        });
    }
};
