<?php

namespace App\Filament\Resources\PalletRules;

use App\Filament\Resources\PalletRules\Pages\ManagePalletRules;
use App\Models\PalletRule;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PalletRuleResource extends Resource
{
    protected static ?string $model = PalletRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Pallet durations';

    protected static ?string $modelLabel = 'pallet rule';

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * @return array<int, string>
     */
    public static function durationOptions(): array
    {
        $options = [];

        for ($minutes = 15; $minutes <= 180; $minutes += 15) {
            $options[$minutes] = self::formatDuration($minutes);
        }

        return $options;
    }

    public static function formatDuration(int $minutes): string
    {
        return "{$minutes} min";
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Select::make('duration_minutes')
                    ->label('Blocked time')
                    ->options(self::durationOptions())
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('max_pallets')
                    ->label('Up to')
                    ->formatStateUsing(fn (int $state): string => "{$state} pallets")
                    ->sortable(),
                TextColumn::make('duration_minutes')
                    ->label('Blocked time')
                    ->formatStateUsing(fn (int $state): string => self::formatDuration($state)),
            ])
            ->defaultSort('max_pallets')
            ->paginated(false)
            ->recordActions([
                EditAction::make()
                    ->modalWidth(Width::Small),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePalletRules::route('/'),
        ];
    }
}
