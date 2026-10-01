<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Category;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Product Info')->schema([
                Forms\Components\Select::make('category_id')
                    ->label('Category')
                    ->options(Category::pluck('name', 'id'))
                    ->searchable()
                    ->required(),

                Forms\Components\Select::make('brand_id')
                    ->label('Brand')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload(),

                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),

                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                Forms\Components\Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),

                Forms\Components\FileUpload::make('image')
                    ->label('Product Image')
                    ->image()
                    ->disk('public')
                    ->visibility('public')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(5120)
                    ->directory('products')
                    ->imageEditor()
                    ->columnSpanFull(),
            ])->columns(2),

            Forms\Components\Section::make('Pricing & Stock')->schema([
                Forms\Components\TextInput::make('price')
                    ->label('Price (AED)')
                    ->numeric()
                    ->required()
                    ->prefix('AED'),

                Forms\Components\TextInput::make('discount_percent')
                    ->label('Discount %')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->maxValue(90)
                    ->suffix('%')
                    ->helperText('0 = "SALE" badge nahi dikhega'),

                Forms\Components\TextInput::make('stock')
                    ->numeric()
                    ->required()
                    ->default(0),

                Forms\Components\TextInput::make('rating')
                    ->numeric()
                    ->default(4.0)
                    ->minValue(0)
                    ->maxValue(5)
                    ->step(0.1),
            ])->columns(4),

            Forms\Components\Section::make('Visibility')->schema([
                Forms\Components\Toggle::make('is_featured')
                    ->label('Featured (Home screen par dikhana hai)'),

                Forms\Components\Toggle::make('is_active')
                    ->label('Active (App mein dikhega)')
                    ->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')->disk('public')->label(''),
                Tables\Columns\TextColumn::make('name')->searchable()->limit(35),
                Tables\Columns\TextColumn::make('category.name')->badge(),
                Tables\Columns\TextColumn::make('price')
                    ->money('AED')
                    ->sortable(),
                Tables\Columns\TextColumn::make('discount_percent')
                    ->label('Discount')
                    ->formatStateUsing(fn ($state) => $state > 0 ? "{$state}% OFF" : '-')
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('stock')
                    ->sortable()
                    ->color(fn ($state) => $state < 10 ? 'danger' : 'success'),
                Tables\Columns\IconColumn::make('is_featured')->boolean(),
                Tables\Columns\ToggleColumn::make('is_active'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Category')
                    ->options(Category::pluck('name', 'id')),
                Tables\Filters\TernaryFilter::make('is_featured'),
                Tables\Filters\TernaryFilter::make('is_active'),
                Tables\Filters\Filter::make('low_stock')
                    ->label('Low Stock (<10)')
                    ->query(fn ($query) => $query->where('stock', '<', 10)),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
