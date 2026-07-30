<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipment extends Model
{
    protected $table = 'equipment';

    protected $fillable = [
        'name', 'slug', 'description', 'image',
        'rent_per_day', 'deposit', 'available_units', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rent_per_day' => 'decimal:2',
            'deposit'      => 'decimal:2',
            'is_active'    => 'boolean',
        ];
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(EquipmentRental::class);
    }
}
