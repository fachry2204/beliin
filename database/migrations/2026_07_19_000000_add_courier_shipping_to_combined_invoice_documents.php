<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('combined_invoice_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('courier_id')->nullable()->after('customer_id');
            $table->string('courier_name')->nullable()->after('courier_id');
            $table->decimal('shipping_cost', 20, 2)->default(0)->after('courier_name');
            $table->foreign('courier_id', 'facture_courier_fk')->references('id')->on('couriers')->restrictOnDelete();
        });

        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('combined_invoice_document_id')->nullable()->after('invoice_id');
            $table->foreign('combined_invoice_document_id', 'cash_facture_shipping_fk')
                ->references('id')->on('combined_invoice_documents')->cascadeOnDelete();
            $table->unique('combined_invoice_document_id', 'cash_facture_shipping_unique');
        });
    }

    public function down(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->dropUnique('cash_facture_shipping_unique');
            $table->dropForeign('cash_facture_shipping_fk');
            $table->dropColumn('combined_invoice_document_id');
        });

        Schema::table('combined_invoice_documents', function (Blueprint $table) {
            $table->dropForeign('facture_courier_fk');
            $table->dropColumn(['courier_id', 'courier_name', 'shipping_cost']);
        });
    }
};
