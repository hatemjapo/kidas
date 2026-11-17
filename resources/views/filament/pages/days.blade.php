<x-filament-panels::page>
    @if ($selectedDate === null)
        <div class="kidas-bar">
            <x-filament::button wire:click="previousMonth" color="gray" icon="heroicon-m-chevron-left">
                Previous
            </x-filament::button>

            <span class="kidas-bar__title">{{ $this->monthLabel() }}</span>

            <x-filament::button wire:click="nextMonth" color="gray" icon="heroicon-m-chevron-right" icon-position="after">
                Next
            </x-filament::button>
        </div>

        <div class="kidas-grid kidas-grid--head">
            @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $label)
                <div>{{ $label }}</div>
            @endforeach
        </div>

        <div class="kidas-grid kidas-grid--days">
            @for ($blank = 0; $blank < $this->leadingBlanks(); $blank++)
                <div class="kidas-blank"></div>
            @endfor

            @foreach ($this->monthDays() as $day)
                <button
                    type="button"
                    wire:click="selectDay('{{ $day['date'] }}')"
                    class="kidas-day @if ($day['isToday']) kidas-day--today @endif @if ($day['isPast']) kidas-day--past @endif"
                    title="{{ $day['isReleased'] ? 'Released' : 'Not released' }}"
                >
                    {{ $day['number'] }}

                    <span class="kidas-day__dot {{ $day['isReleased'] ? 'kidas-day__dot--on' : 'kidas-day__dot--off' }}"></span>
                </button>
            @endforeach
        </div>
    @else
        <div class="kidas-bar">
            <x-filament::button wire:click="previousDay" color="gray" icon="heroicon-m-chevron-left">
                Previous day
            </x-filament::button>

            <span class="kidas-bar__title">{{ $this->selectedDayLabel() }}</span>

            <x-filament::button wire:click="nextDay" color="gray" icon="heroicon-m-chevron-right" icon-position="after">
                Next day
            </x-filament::button>
        </div>

        @if ($this->isPast())
            <p class="kidas-note">This day is in the past and cannot be changed.</p>
        @endif

        <form wire:submit="save">
            {{ $this->form }}

            <div class="kidas-actions">
                <x-filament::button type="submit" :disabled="$this->isPast()">
                    Save
                </x-filament::button>

                <x-filament::button wire:click="backToMonth" color="gray" type="button">
                    Back to the month
                </x-filament::button>
            </div>
        </form>
    @endif
</x-filament-panels::page>
