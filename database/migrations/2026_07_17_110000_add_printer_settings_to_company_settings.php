<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('printer_type')->default('dot_matrix');
            $table->string('printer_paper_size')->default('a5');
            $table->string('printer_orientation')->default('portrait');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['printer_type', 'printer_paper_size', 'printer_orientation']);
        });
    }
};
