<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courier_deliveries', function (Blueprint $table) {
            $table->decimal('departed_latitude', 10, 7)->nullable()->after('departed_at');
            $table->decimal('departed_longitude', 10, 7)->nullable()->after('departed_latitude');
            $table->decimal('departed_accuracy', 10, 2)->nullable()->after('departed_longitude');
            $table->text('departure_address')->nullable()->after('departed_accuracy');
            $table->string('departure_photo_path')->nullable()->after('departure_address');
            $table->timestamp('departure_photo_taken_at')->nullable()->after('departure_photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('courier_deliveries', function (Blueprint $table) {
            $table->dropColumn([
                'departed_latitude',
                'departed_longitude',
                'departed_accuracy',
                'departure_address',
                'departure_photo_path',
                'departure_photo_taken_at',
            ]);
        });
    }
};
