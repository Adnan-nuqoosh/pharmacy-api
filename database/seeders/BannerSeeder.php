<?php

namespace Database\Seeders;

use App\Models\Banner;
use Illuminate\Database\Seeder;

class BannerSeeder extends Seeder
{
    /**
     * Design ke home banners. Chalayein: php artisan db:seed --class=BannerSeeder
     */
    public function run(): void
    {
        $banners = [
            [
                'title'      => 'SUMMER SAVERS',
                'subtitle'   => 'Up to 50% OFF on Vitamins & Beauty essentials',
                'link'       => '/products?on_sale=1',
                'sort_order' => 1,
            ],
            [
                'title'      => 'SPORTS NUTRITION',
                'subtitle'   => '30% OFF on all products - Use code FIT30',
                'link'       => '/products?category=vitamins&on_sale=1',
                'sort_order' => 2,
            ],
        ];

        foreach ($banners as $banner) {
            Banner::firstOrCreate(['title' => $banner['title']], $banner);
        }
    }
}
