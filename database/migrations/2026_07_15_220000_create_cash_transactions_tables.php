<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_transaction_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10);
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['type', 'year', 'month']);
        });

        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('transaction_number', 50)->unique();
            $table->string('type', 10)->index();
            $table->date('transaction_date')->index();
            $table->string('category', 100)->index();
            $table->string('description', 255);
            $table->string('payment_method', 30);
            $table->decimal('amount', 20, 2);
            $table->string('reference_number', 150)->nullable()->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_transactions');
        Schema::dropIfExists('cash_transaction_sequences');
    }
};
