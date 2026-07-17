<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courier_shipping_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('courier_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 20, 2);
            $table->timestamp('paid_at')->nullable()->index();
            $table->foreignId('cash_transaction_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['courier_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_shipping_deposits');
    }
};
