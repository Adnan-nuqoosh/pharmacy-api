<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use \App\Models\Concerns\HasCatalogImage, HasFactory;

    protected $fillable = [
        'category_id',
        'brand_id',
        'name',
        'slug',
        'description',
        'ingredients',      // Product Details screen
        'brand_name',       // Product Details screen
        'expiry_date',      // Product Details screen
        'price',
        'vat_inclusive',    // "Inclusive of VAT"
        'discount_percent',
        'image',
        'rating',
        'reviews_count',
        'stock',
        'is_featured',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'rating' => 'decimal:1',
            'expiry_date' => 'date:Y-m-d',
            'vat_inclusive' => 'boolean',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected $appends = ['final_price', 'on_sale', 'image_url'];

    protected function finalPrice(): Attribute
    {
        return Attribute::get(
            fn () => round($this->price * (1 - $this->discount_percent / 100), 2)
        );
    }

    protected function onSale(): Attribute
    {
        return Attribute::get(fn () => $this->discount_percent > 0);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            if ($product->brand_id) {
                $product->brand_name = Brand::whereKey($product->brand_id)->value('name');
            } elseif ($product->isDirty('brand_id') && ! $product->isDirty('brand_name')) {
                $product->brand_name = null;
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Reviews add/update/delete hone par product ka rating aur count refresh karta hai.
     * Design: "4.2 — 923 Ratings and 257 Reviews"
     */
    public function recalculateRating(): void
    {
        $approved = $this->reviews()->where('is_approved', true);

        $this->update([
            'rating' => round((float) $approved->avg('rating'), 1),
            'reviews_count' => $approved->count(),
        ]);
    }

    /**
     * Star breakdown — design mein 5★=20%, 4★=67% waghera.
     */
    public function ratingBreakdown(): array
    {
        $counts = $this->reviews()
            ->where('is_approved', true)
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating')
            ->toArray();

        $totalReviews = array_sum($counts);
        $breakdown = [];

        for ($star = 5; $star >= 1; $star--) {
            $count = $counts[$star] ?? 0;
            $breakdown[] = [
                'star' => $star,
                'count' => $count,
                'percent' => $totalReviews > 0 ? round(($count / $totalReviews) * 100) : 0,
            ];
        }

        return $breakdown;
    }
}
