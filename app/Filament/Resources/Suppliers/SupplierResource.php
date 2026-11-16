<?php

namespace App\Filament\Resources\Suppliers;

use App\Actions\Suppliers\DecideSupplierRegistration;
use App\Enums\SupplierStatus;
use App\Filament\Resources\Suppliers\Pages\ManageSuppliers;
use App\Models\Supplier;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static ?string $navigationLabel = 'Supplier registrations';

    protected static ?string $modelLabel = 'registration';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company_name')
                    ->label('Company')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('company_phone')
                    ->label('Phone'),
                TextColumn::make('user.name')
                    ->label('Contact')
                    ->searchable(),
                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (SupplierStatus $state): string => match ($state) {
                        SupplierStatus::Pending => 'warning',
                        SupplierStatus::Approved => 'success',
                        SupplierStatus::Rejected => 'danger',
                    }),
                TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('approvedBy.name')
                    ->label('Approved by')
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(SupplierStatus::class)
                    ->default(SupplierStatus::Pending->value),
            ])
            ->recordActions([
                Action::make('approve')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('The supplier can sign in afterwards and will receive an email.')
                    ->visible(fn (Supplier $record): bool => $record->status === SupplierStatus::Pending)
                    ->action(function (Supplier $record): void {
                        $admin = auth()->user();
                        assert($admin instanceof User);

                        app(DecideSupplierRegistration::class)->approve($record, $admin);

                        Notification::make()
                            ->success()
                            ->title('Registration approved')
                            ->send();
                    }),
                Action::make('reject')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('The supplier stays locked out and will receive an email.')
                    ->visible(fn (Supplier $record): bool => $record->status === SupplierStatus::Pending)
                    ->action(function (Supplier $record): void {
                        app(DecideSupplierRegistration::class)->reject($record);

                        Notification::make()
                            ->success()
                            ->title('Registration rejected')
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSuppliers::route('/'),
        ];
    }
}
