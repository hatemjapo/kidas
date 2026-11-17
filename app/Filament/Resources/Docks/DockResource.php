<?php

namespace App\Filament\Resources\Docks;

use App\Filament\Resources\Docks\Pages\ManageDocks;
use App\Models\Dock;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class DockResource extends Resource
{
    protected static ?string $model = Dock::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

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
                TextColumn::make('number')
                    ->label('Number')
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Name'),
                ToggleColumn::make('is_active')
                    ->label('Active'),
            ])
            ->defaultSort('number')
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDocks::route('/'),
        ];
    }
}
