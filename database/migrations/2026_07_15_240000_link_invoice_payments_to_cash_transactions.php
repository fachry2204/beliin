<?php

use App\Models\Payment;
use App\Services\CashTransactionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->foreignId('payment_id')->nullable()->after('id')->unique()->constrained()->restrictOnDelete();
        });

        Payment::query()
            ->with('invoice')
            ->orderBy('payment_date')
            ->orderBy('id')
            ->each(fn (Payment $payment) => app(CashTransactionService::class)->createFromPayment($payment));
    }

    public function down(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_id');
        });
    }
};
