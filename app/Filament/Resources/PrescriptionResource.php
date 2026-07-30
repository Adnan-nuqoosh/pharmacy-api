<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PrescriptionResource\Pages;
use App\Models\Prescription;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PrescriptionResource extends Resource
{
    protected static ?string $model = Prescription::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 2;

    // Naye records app se aate hain, admin panel se nahi
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('user.name')->label('Customer')->disabled(),

            Forms\Components\FileUpload::make('image_path')
                ->label('Prescription Image')
                ->image()
                ->disabled()
                ->disk('public'),

            Forms\Components\Textarea::make('notes')
                ->label('Customer Notes')
                ->disabled()
                ->columnSpanFull(),

            Forms\Components\Select::make('status')
                ->options([
                    'pending'  => 'Pending',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ])
                ->required(),

            Forms\Components\Textarea::make('admin_remarks')
                ->label('Aapka jawab / remarks customer ke liye')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')->label('Image')->disk('public'),
                Tables\Columns\TextColumn::make('user.name')->label('Customer')->searchable(),
                Tables\Columns\TextColumn::make('notes')->limit(40),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger'  => 'rejected',
                    ]),
                Tables\Columns\TextColumn::make('created_at')->dateTime('d M, h:i A')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'pending'  => 'Pending',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ]),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Prescription $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function (Prescription $record) {
                        $record->update(['status' => 'approved']);
                        Notification::make()->title('Prescription approved')->success()->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (Prescription $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\Textarea::make('admin_remarks')->label('Reject karne ki wajah'),
                    ])
                    ->action(function (Prescription $record, array $data) {
                        $record->update(['status' => 'rejected', 'admin_remarks' => $data['admin_remarks']]);
                        Notification::make()->title('Prescription rejected')->danger()->send();
                    }),

                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrescriptions::route('/'),
            'edit'  => Pages\EditPrescription::route('/{record}/edit'),
        ];
    }
}
