<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorSlot extends Model
{
    protected $fillable = ['doctor_id', 'date', 'start_time', 'end_time', 'is_booked'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'is_booked' => 'boolean'];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }
}
