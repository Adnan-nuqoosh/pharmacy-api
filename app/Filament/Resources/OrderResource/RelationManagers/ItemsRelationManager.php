<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Order Items';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('product_name')
            ->columns([
                Tables\Columns\TextColumn::make('product_name'),
                Tables\Columns\TextColumn::make('price')->money('AED'),
                Tables\Columns\TextColumn::make('discount_percent')->suffix('%'),
                Tables\Columns\TextColumn::make('final_price')->money('AED'),
                Tables\Columns\TextColumn::make('quantity'),
                Tables\Columns\TextColumn::make('line_total')->money('AED'),
            ])
            ->headerActions([]) // items add/edit yahan se nahi hote, order ke saath ban chuke
            ->actions([]);
    }
}
