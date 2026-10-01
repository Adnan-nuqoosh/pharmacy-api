<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use \App\Models\Concerns\HasCatalogImage, HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'is_active',
        'sort_order',
    ];

    protected $appends = ['image', 'image_url', 'icon_url'];

    protected function catalogImageColumn(): string
    {
        return 'icon';
    }

    protected function image(): Attribute
    {
        return Attribute::get(fn () => $this->icon);
    }

    protected function iconUrl(): Attribute
    {
        return Attribute::get(fn () => $this->image_url);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
