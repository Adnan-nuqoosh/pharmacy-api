<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    /**
     * Design ke home screen wali categories + sample products.
     * Chalane ke liye: php artisan db:seed --class=CatalogSeeder
     */
    public function run(): void
    {
        // ---- Categories (Figma design se) ----
        $categories = [
            'Medicines', 'Vitamins', 'Baby Care', 'Beauty',
            'Eye Care', 'Diabetes Care', 'Ayurveda', 'First Aid',
        ];

        foreach ($categories as $i => $name) {
            Category::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'sort_order' => $i + 1]
            );
        }

        // ---- Sample Products (Figma design ke cards se) ----
        $products = [
            ['Nizoral Anti Dandruff Shampoo 100ml',            'Beauty',    37.95, 0,  4.2, true],
            ['Bepanthen 5% Cream 30g Tube',                    'Medicines', 35.00, 0,  4.2, true],
            ['Accu-check Active Test Strip',                   'Diabetes Care', 112.00, 0, 4.2, true],
            ['Esse Naturals Lip Balm Blueberry Blush 4.8g',    'Beauty',    15.75, 15, 4.2, true],
            ['Skin1004 Centella Light Cleansing Oil 200ml',    'Beauty',    38.85, 15, 4.2, true],
            ['Shiseido Fino Premium Touch Mask',               'Beauty',    24.68, 15, 4.2, false],
            ['Muscle Core BCAA 530mg 90 Capsules',             'Vitamins',   9.45, 15, 4.2, true],
            ['Pure Protein Bar Chocolate Peanut Butter',       'Vitamins',  75.60, 15, 4.2, true],
            ['Sunshine Nutrition Effervescent 20 Tablets',     'Vitamins',  30.45, 15, 4.2, false],
        ];

        foreach ($products as [$name, $catName, $price, $discount, $rating, $featured]) {
            $category = Category::where('name', $catName)->first();

            Product::firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'category_id'      => $category->id,
                    'name'             => $name,
                    'description'      => "High quality {$catName} product available at Abwab Al Kheir Pharmacy.",
                    'price'            => $price,
                    'discount_percent' => $discount,
                    'rating'           => $rating,
                    'stock'            => 50,
                    'is_featured'      => $featured,
                ]
            );
        }
    }
}
