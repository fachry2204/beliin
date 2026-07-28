<?php

use App\Models\CompanySetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->json('cash_out_categories')->nullable()->after('printer_orientation');
        });

        DB::table('company_settings')
            ->whereNull('cash_out_categories')
            ->update([
                'cash_out_categories' => json_encode(
                    CompanySetting::DEFAULT_CASH_OUT_CATEGORIES,
                    JSON_UNESCAPED_UNICODE
                ),
            ]);
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('cash_out_categories');
        });
    }
};
