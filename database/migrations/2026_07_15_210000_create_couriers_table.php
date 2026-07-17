<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('couriers', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('courier_code', 50)->unique();
            $table->string('name', 150)->index();
            $table->string('phone', 30)->nullable();
            $table->string('vehicle_type', 100)->nullable();
            $table->string('license_plate', 20)->nullable()->index();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('couriers');
    }
};
