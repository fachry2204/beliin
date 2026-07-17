<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_login_at')->nullable();
        });

        Schema::create('company_settings', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('company_name');
            $table->string('logo')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->text('invoice_footer')->nullable();
            $table->string('invoice_prefix', 20)->default('INV');
            $table->decimal('default_tax_percentage', 8, 4)->default(11);
            $table->boolean('shipping_is_revenue')->default(false);
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('customer_code')->unique();
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('email')->nullable();
            $table->string('tax_number')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('suppliers', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('supplier_code')->unique();
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('email')->nullable();
            $table->string('tax_number')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('product_categories', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('category_id')->constrained('product_categories')->restrictOnDelete();
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->unique();
            $table->string('name')->index();
            $table->text('description')->nullable();
            $table->string('unit', 30);
            $table->decimal('purchase_price', 20, 2)->default(0);
            $table->decimal('average_purchase_price', 20, 2)->default(0);
            $table->decimal('selling_price', 20, 2)->default(0);
            $table->decimal('stock', 15, 4)->default(0);
            $table->decimal('minimum_stock', 15, 4)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('incoming_transactions', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('transaction_number')->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->date('transaction_date')->index();
            $table->string('supplier_invoice_number')->nullable()->index();
            $table->string('purchase_order_number')->nullable();
            $table->decimal('subtotal', 20, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('attachment')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('incoming_transaction_items', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('incoming_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('product_name_snapshot');
            $table->decimal('purchase_price', 20, 2);
            $table->decimal('quantity', 15, 4);
            $table->decimal('volume', 15, 4)->default(1);
            $table->string('unit', 30);
            $table->string('calculation_method', 20)->default('qty');
            $table->decimal('line_total', 20, 2);
            $table->timestamps();
        });
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['year', 'month']);
        });
        Schema::create('invoices', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->date('invoice_date')->index();
            $table->date('due_date')->index();
            $table->string('purchase_order_number')->nullable();
            $table->string('billing_name');
            $table->string('billing_company')->nullable();
            $table->string('billing_phone')->nullable();
            $table->string('billing_email')->nullable();
            $table->text('billing_address')->nullable();
            $table->decimal('subtotal', 20, 2);
            $table->string('discount_type', 20)->default('nominal');
            $table->decimal('discount_value', 20, 2)->default(0);
            $table->decimal('discount_amount', 20, 2)->default(0);
            $table->decimal('tax_percentage', 8, 4)->default(0);
            $table->decimal('tax_amount', 20, 2)->default(0);
            $table->decimal('shipping_cost', 20, 2)->default(0);
            $table->decimal('grand_total', 20, 2);
            $table->decimal('total_cost', 20, 2)->default(0);
            $table->decimal('gross_profit', 20, 2)->default(0);
            $table->decimal('paid_amount', 20, 2)->default(0);
            $table->decimal('remaining_amount', 20, 2)->default(0);
            $table->string('status', 30)->default('draft')->index();
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('product_name_snapshot');
            $table->string('sku_snapshot');
            $table->string('unit_snapshot', 30);
            $table->decimal('purchase_price', 20, 2);
            $table->decimal('selling_price', 20, 2);
            $table->decimal('quantity', 15, 4);
            $table->decimal('volume', 15, 4)->default(1);
            $table->string('calculation_method', 20)->default('qty');
            $table->decimal('line_subtotal', 20, 2);
            $table->decimal('cost_total', 20, 2);
            $table->decimal('profit', 20, 2);
            $table->timestamps();
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('payment_number')->unique();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->date('payment_date')->index();
            $table->decimal('amount', 20, 2);
            $table->string('payment_method', 30);
            $table->string('bank_name')->nullable();
            $table->string('reference_number')->nullable()->index();
            $table->string('payment_proof')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id');
            $table->string('movement_type', 20)->index();
            $table->decimal('quantity', 15, 4);
            $table->decimal('stock_before', 15, 4);
            $table->decimal('stock_after', 15, 4);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['reference_type', 'reference_id']);
        });
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action')->index();
            $table->string('module')->index();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->json('old_data')->nullable();
            $table->json('new_data')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('invoice_sequences');
        Schema::dropIfExists('incoming_transaction_items');
        Schema::dropIfExists('incoming_transactions');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('company_settings');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['is_active', 'last_login_at']));
    }
};
