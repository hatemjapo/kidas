<?php

namespace App\Filament\Resources\ShiftDefaults;

use App\Enums\Shift;
use App\Filament\Resources\ShiftDefaults\Pages\ManageShiftDefaults;
use App\Models\ShiftDefault;
use App\Rules\DoesNotOverlapOtherShift;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ShiftDefaultResource extends Resource
{
    protected static ?string $model = ShiftDefault::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static ?string $navigationLabel = 'Shift times';

    protected static ?string $modelLabel = 'shift';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TimePicker::make('starts_at')
                    ->label('Starts at')
                    ->seconds(false)
                    ->required(),
                TimePicker::make('ends_at')
                    ->label('Ends at')
                    ->seconds(false)
                    ->required()
                    ->after('starts_at')
                    ->rules(fn (ShiftDefault $record, Get $get): array => [
                        new DoesNotOverlapOtherShift($record->shift, (string) $get('starts_at')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('shift')
                    ->label('Shift')
                    ->formatStateUsing(fn (Shift $state): string => $state->label()),
                TextColumn::make('starts_at')
                    ->label('Starts at')
                    ->time('H:i'),
                TextColumn::make('ends_at')
                    ->label('Ends at')
                    ->time('H:i'),
            ])
            ->defaultSort('starts_at')
            ->paginated(false)
            ->recordActions([
                EditAction::make()
                    ->modalWidth(Width::Small),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageShiftDefaults::route('/'),
        ];
    }
}
