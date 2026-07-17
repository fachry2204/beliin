<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('couriers', function (Blueprint $table) {
            $table->boolean('is_online')->default(false)->index()->after('is_active');
        });

        if (Schema::hasTable('sessions')) {
            $loggedInUserIds = DB::table('sessions')->whereNotNull('user_id')->distinct()->pluck('user_id');
            DB::table('couriers')->whereIn('user_id', $loggedInUserIds)->update(['is_online' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('couriers', function (Blueprint $table) {
            $table->dropColumn('is_online');
        });
    }
};
