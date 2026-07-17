<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Courier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['user_id', 'courier_code', 'name', 'phone', 'vehicle_type', 'license_plate', 'bank_name', 'bank_account_number', 'bank_account_name', 'notes', 'is_active', 'is_online', 'last_latitude', 'last_longitude', 'last_location_accuracy', 'last_location_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_online' => 'boolean', 'last_latitude' => 'decimal:7', 'last_longitude' => 'decimal:7', 'last_location_accuracy' => 'decimal:2', 'last_location_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function deliveries()
    {
        return $this->hasMany(CourierDelivery::class);
    }

    public function locations()
    {
        return $this->hasMany(CourierLocation::class);
    }

    public function shippingDeposits()
    {
        return $this->hasMany(CourierShippingDeposit::class);
    }
}
