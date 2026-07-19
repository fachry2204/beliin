<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->foreignId('invoice_cost_id')
                ->nullable()
                ->after('invoice_id')
                ->constrained('invoices')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->dropForeign(['invoice_cost_id']);
            $table->dropColumn('invoice_cost_id');
        });
    }
};
