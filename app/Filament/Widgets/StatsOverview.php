<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Prescription;
use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Today\'s Orders', Order::whereDate('created_at', today())->count())
                ->description('Aaj ke orders')
                ->color('success'),

            Stat::make('Pending Orders', Order::where('status', 'pending')->count())
                ->description('Confirm karne wale')
                ->color('warning'),

            Stat::make('Pending Prescriptions', Prescription::where('status', 'pending')->count())
                ->description('Review karne wale')
                ->color('danger'),

            Stat::make('Low Stock Products', Product::where('stock', '<', 10)->count())
                ->description('10 se kam stock')
                ->color('gray'),
        ];
    }
}
