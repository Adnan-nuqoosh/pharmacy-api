<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    protected $fillable = [
        'appointment_number', 'user_id', 'doctor_id', 'doctor_slot_id',
        'date', 'time', 'patient_name', 'patient_phone', 'reason', 'fee', 'status',
    ];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'fee' => 'decimal:2'];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(DoctorSlot::class, 'doctor_slot_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
