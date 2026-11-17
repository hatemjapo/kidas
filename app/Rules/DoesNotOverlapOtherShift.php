<?php

namespace App\Rules;

use App\Enums\Shift;
use App\Models\ShiftDefault;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class DoesNotOverlapOtherShift implements ValidationRule
{
    public function __construct(
        private readonly Shift $shift,
        private readonly string $startsAt,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '' || $this->startsAt === '') {
            return;
        }

        $starts = $this->minutes($this->startsAt);
        $ends = $this->minutes($value);

        $others = ShiftDefault::query()
            ->where('shift', '!=', $this->shift)
            ->get();

        foreach ($others as $other) {
            $otherStarts = $this->minutes($other->starts_at);
            $otherEnds = $this->minutes($other->ends_at);

            if ($starts < $otherEnds && $otherStarts < $ends) {
                $fail("This overlaps the {$other->shift->label()} from {$this->format($other->starts_at)} to {$this->format($other->ends_at)}.");

                return;
            }
        }
    }

    private function minutes(string $time): int
    {
        $parts = explode(':', $time);

        return ((int) $parts[0]) * 60 + ((int) ($parts[1] ?? 0));
    }

    private function format(string $time): string
    {
        $parts = explode(':', $time);

        return sprintf('%02d:%02d', (int) $parts[0], (int) ($parts[1] ?? 0));
    }
}
