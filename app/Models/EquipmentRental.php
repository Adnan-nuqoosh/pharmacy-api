<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentRental extends Model
{
    protected $fillable = [
        'rental_number', 'user_id', 'equipment_id', 'address_id',
        'start_date', 'end_date', 'days', 'quantity',
        'rent_total', 'deposit', 'total', 'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date'   => 'date:Y-m-d',
            'rent_total' => 'decimal:2',
            'deposit'    => 'decimal:2',
            'total'      => 'decimal:2',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
