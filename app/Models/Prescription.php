<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Prescription extends Model
{
    protected $fillable = [
        'user_id',
        'request_type',           // with_prescription | without_prescription
        'image_path',
        'emirates_id_front',
        'emirates_id_back',
        'insurance_card_front',
        'insurance_card_back',
        'address_id',
        'payment_method',         // online | cod
        'delivery_preference',
        'notes',
        'status',
        'admin_remarks',
    ];

    // Frontend ko sab image URLs ready-made milein
    protected $appends = [
        'image_url',
        'emirates_id_front_url',
        'emirates_id_back_url',
        'insurance_card_front_url',
        'insurance_card_back_url',
    ];

    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->url($this->image_path));
    }

    protected function emiratesIdFrontUrl(): Attribute
    {
        return Attribute::get(fn () => $this->url($this->emirates_id_front));
    }

    protected function emiratesIdBackUrl(): Attribute
    {
        return Attribute::get(fn () => $this->url($this->emirates_id_back));
    }

    protected function insuranceCardFrontUrl(): Attribute
    {
        return Attribute::get(fn () => $this->url($this->insurance_card_front));
    }

    protected function insuranceCardBackUrl(): Attribute
    {
        return Attribute::get(fn () => $this->url($this->insurance_card_back));
    }

    private function url(?string $path): ?string
    {
        return $path ? Storage::url($path) : null;
    }

    // ---------- Relations ----------

    public function images(): HasMany
    {
        return $this->hasMany(PrescriptionImage::class);
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
