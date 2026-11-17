<?php

namespace App\Filament\Resources\OpeningDefaults;

use App\Enums\Weekday;
use App\Filament\Resources\OpeningDefaults\Pages\ManageOpeningDefaults;
use App\Models\OpeningDefault;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OpeningDefaultResource extends Resource
{
    protected static ?string $model = OpeningDefault::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Opening hours';

    protected static ?string $modelLabel = 'opening hours';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Toggle::make('is_closed')
                    ->label('Closed all day')
                    ->live(),
                TimePicker::make('opens_at')
                    ->label('Opens at')
                    ->seconds(false)
                    ->required()
                    ->hidden(fn (Get $get): bool => (bool) $get('is_closed')),
                TimePicker::make('closes_at')
                    ->label('Closes at')
                    ->seconds(false)
                    ->required()
                    ->after('opens_at')
                    ->hidden(fn (Get $get): bool => (bool) $get('is_closed')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('weekday')
                    ->label('Weekday')
                    ->formatStateUsing(fn (Weekday $state): string => $state->label()),
                TextColumn::make('opens_at')
                    ->label('Opens at')
                    ->time('H:i')
                    ->placeholder('—'),
                TextColumn::make('closes_at')
                    ->label('Closes at')
                    ->time('H:i')
                    ->placeholder('—'),
                IconColumn::make('is_closed')
                    ->label('Closed')
                    ->boolean(),
            ])
            ->defaultSort('weekday')
            ->paginated(false)
            ->recordActions([
                EditAction::make()
                    ->modalWidth(Width::Small),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOpeningDefaults::route('/'),
        ];
    }
}
