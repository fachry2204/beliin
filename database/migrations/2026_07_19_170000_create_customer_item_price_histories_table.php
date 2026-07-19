<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_item_price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('line_number');
            $table->string('item_key', 100);
            $table->string('product_name', 255);
            $table->string('sku', 100)->nullable();
            $table->string('unit', 30);
            $table->decimal('purchase_price', 20, 2);
            $table->decimal('selling_price', 20, 2);
            $table->date('invoice_date')->index();
            $table->timestamp('recorded_at')->index();
            $table->timestamps();

            $table->unique(['invoice_id', 'line_number']);
            $table->index(['customer_id', 'item_key', 'recorded_at'], 'customer_item_price_latest_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_item_price_histories');
    }
};
