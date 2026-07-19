<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->boolean('backup_auto_enabled')->default(false);
            $table->string('backup_auto_type')->default('database');
            $table->string('backup_auto_frequency')->default('daily');
            $table->string('backup_auto_time', 5)->default('01:00');
            $table->unsignedTinyInteger('backup_retention_count')->default(7);
            $table->timestamp('backup_last_run_at')->nullable();
            $table->text('backup_last_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn([
                'backup_auto_enabled', 'backup_auto_type', 'backup_auto_frequency',
                'backup_auto_time', 'backup_retention_count', 'backup_last_run_at',
                'backup_last_error',
            ]);
        });
    }
};
