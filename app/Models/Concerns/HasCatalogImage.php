<?php

namespace App\Models\Concerns;

use App\Support\CatalogImage;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasCatalogImage
{
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => CatalogImage::url($this->getAttribute($this->catalogImageColumn())));
    }

    protected function catalogImageColumn(): string
    {
        return 'image';
    }
}
