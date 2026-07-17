<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('couriers', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->unique()->after('id')->constrained()->nullOnDelete();
            $table->decimal('last_latitude', 10, 7)->nullable()->after('is_active');
            $table->decimal('last_longitude', 10, 7)->nullable()->after('last_latitude');
            $table->decimal('last_location_accuracy', 10, 2)->nullable()->after('last_longitude');
            $table->timestamp('last_location_at')->nullable()->index()->after('last_location_accuracy');
        });

        Schema::create('courier_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('courier_id')->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('pending')->index();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('departed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->decimal('accepted_latitude', 10, 7)->nullable();
            $table->decimal('accepted_longitude', 10, 7)->nullable();
            $table->decimal('delivered_latitude', 10, 7)->nullable();
            $table->decimal('delivered_longitude', 10, 7)->nullable();
            $table->decimal('delivered_accuracy', 10, 2)->nullable();
            $table->string('proof_photo_path')->nullable();
            $table->timestamp('proof_taken_at')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->timestamps();

            $table->index(['courier_id', 'status']);
        });

        Schema::create('courier_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('courier_id')->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy', 10, 2)->nullable();
            $table->timestamp('recorded_at')->index();
            $table->timestamps();

            $table->index(['courier_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_locations');
        Schema::dropIfExists('courier_deliveries');

        Schema::table('couriers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['last_latitude', 'last_longitude', 'last_location_accuracy', 'last_location_at']);
        });
    }
};
