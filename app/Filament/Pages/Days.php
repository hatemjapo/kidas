<?php

namespace App\Filament\Pages;

use App\Actions\Days\ResolveDayDefaults;
use App\Actions\Days\SaveDay;
use App\Enums\DockUsage;
use App\Models\CalendarDay;
use App\Models\Dock;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class Days extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.days';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Days';

    protected static ?string $title = 'Days';

    public string $month;

    public ?string $selectedDate = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', CalendarDay::class);
    }

    public function mount(): void
    {
        $this->month = CarbonImmutable::today()->format('Y-m');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->disabled(fn (): bool => $this->isPast())
            ->components([
                Section::make('Day')
                    ->columns(2)
                    ->components([
                        Toggle::make('is_released')
                            ->label('Released for booking'),
                        Toggle::make('is_closed')
                            ->label('Closed all day')
                            ->live(),
                    ]),
                Section::make('Opening hours')
                    ->columns(2)
                    ->components([
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
                    ]),
                Section::make('Shift times')
                    ->columns(4)
                    ->components([
                        TimePicker::make('early_start')
                            ->label('Early from')
                            ->seconds(false)
                            ->required(),
                        TimePicker::make('early_end')
                            ->label('Early to')
                            ->seconds(false)
                            ->required()
                            ->after('early_start'),
                        TimePicker::make('late_start')
                            ->label('Late from')
                            ->seconds(false)
                            ->required()
                            ->afterOrEqual('early_end'),
                        TimePicker::make('late_end')
                            ->label('Late to')
                            ->seconds(false)
                            ->required()
                            ->after('late_start'),
                    ]),
                Section::make('Docks')
                    ->columns(2)
                    ->components($this->dockFields()),
            ]);
    }

    /**
     * @return array<int, Fieldset>
     */
    private function dockFields(): array
    {
        return Dock::getActiveDocks()
            ->map(fn (Dock $dock): Fieldset => Fieldset::make($dock->name)
                ->columns(2)
                ->components([
                    Select::make("usages.{$dock->id}.early")
                        ->label('Early shift')
                        ->options(DockUsage::options())
                        ->required(),
                    Select::make("usages.{$dock->id}.late")
                        ->label('Late shift')
                        ->options(DockUsage::options())
                        ->required(),
                ]))
            ->all();
    }

    public function selectDay(string $date): void
    {
        $this->selectedDate = CarbonImmutable::parse($date)->toDateString();

        $this->getForm('form')?->fill(app(ResolveDayDefaults::class)(CarbonImmutable::parse($this->selectedDate)));
    }

    public function backToMonth(): void
    {
        $this->selectedDate = null;
    }

    public function previousMonth(): void
    {
        $this->month = CarbonImmutable::parse($this->month.'-01')->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = CarbonImmutable::parse($this->month.'-01')->addMonth()->format('Y-m');
    }

    public function previousDay(): void
    {
        $this->selectDay(CarbonImmutable::parse($this->selectedDate)->subDay()->toDateString());
    }

    public function nextDay(): void
    {
        $this->selectDay(CarbonImmutable::parse($this->selectedDate)->addDay()->toDateString());
    }

    public function save(): void
    {
        $date = CarbonImmutable::parse($this->selectedDate);

        $form = $this->getForm('form');

        if ($form === null) {
            return;
        }

        app(SaveDay::class)($date, $form->getState());

        Notification::make()
            ->success()
            ->title('Day saved')
            ->send();
    }

    public function isPast(): bool
    {
        return $this->selectedDate !== null
            && CarbonImmutable::parse($this->selectedDate)->isBefore(CarbonImmutable::today());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function monthDays(): array
    {
        $first = CarbonImmutable::parse($this->month.'-01');
        $today = CarbonImmutable::today();

        $released = CalendarDay::getReleasedDatesInMonth($first);

        $days = [];

        foreach (range(1, (int) $first->endOfMonth()->format('j')) as $number) {
            $date = $first->addDays($number - 1);

            $days[] = [
                'date' => $date->toDateString(),
                'number' => $number,
                'isReleased' => in_array($date->toDateString(), $released, true),
                'isPast' => $date->isBefore($today),
                'isToday' => $date->isSameDay($today),
            ];
        }

        return $days;
    }

    public function monthLabel(): string
    {
        return CarbonImmutable::parse($this->month.'-01')->format('F Y');
    }

    public function leadingBlanks(): int
    {
        return CarbonImmutable::parse($this->month.'-01')->isoWeekday() - 1;
    }

    public function selectedDayLabel(): string
    {
        return CarbonImmutable::parse($this->selectedDate)->format('l, j F Y');
    }
}
