<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourierDelivery extends Model
{
    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    public const IN_TRANSIT = 'in_transit';

    public const DELIVERED = 'delivered';

    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'invoice_id', 'courier_id', 'status', 'accepted_at', 'departed_at', 'delivered_at',
        'accepted_latitude', 'accepted_longitude', 'delivered_latitude', 'delivered_longitude',
        'delivered_accuracy', 'delivery_address', 'proof_photo_path', 'proof_taken_at', 'delivery_notes',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime', 'departed_at' => 'datetime', 'delivered_at' => 'datetime',
            'proof_taken_at' => 'datetime', 'accepted_latitude' => 'decimal:7',
            'accepted_longitude' => 'decimal:7', 'delivered_latitude' => 'decimal:7',
            'delivered_longitude' => 'decimal:7', 'delivered_accuracy' => 'decimal:2',
        ];
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function courier()
    {
        return $this->belongsTo(Courier::class);
    }
}
