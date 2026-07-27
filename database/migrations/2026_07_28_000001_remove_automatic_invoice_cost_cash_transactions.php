<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cash_transactions', 'invoice_cost_id')) {
            return;
        }

        // Harga modal adalah dasar perhitungan margin, bukan bukti kas benar-benar keluar.
        // Hanya transaksi turunan invoice yang dibersihkan; Kas Keluar manual tetap disimpan.
        DB::table('cash_transactions')
            ->whereNotNull('invoice_cost_id')
            ->delete();
    }

    public function down(): void
    {
        // Transaksi turunan tidak dibuat ulang karena bukan arus kas aktual.
    }
};
