<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->boolean('tax_enabled')->default(true)->after('default_tax_percentage');
            $table->boolean('discount_enabled')->default(true)->after('tax_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['tax_enabled', 'discount_enabled']);
        });
    }
};
