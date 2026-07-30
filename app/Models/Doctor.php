<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doctor extends Model
{
    protected $fillable = [
        'name', 'speciality', 'qualification', 'image', 'about',
        'experience_years', 'consultation_fee', 'rating', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'consultation_fee' => 'decimal:2',
            'rating'           => 'decimal:1',
            'is_active'        => 'boolean',
        ];
    }

    public function slots(): HasMany
    {
        return $this->hasMany(DoctorSlot::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
