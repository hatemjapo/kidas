<?php

namespace App\Models;

use App\Enums\SupplierStatus;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\LengthAwarePaginator;

#[Fillable(['company_name', 'company_phone'])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SupplierStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return LengthAwarePaginator<int, Booking>
     */
    public function getBookingsPage(int $perPage): LengthAwarePaginator
    {
        return $this->bookings()
            ->with('dock')
            ->orderByDesc('starts_at')
            ->paginate($perPage);
    }

    public function getBookingWithHistory(int $bookingId): Booking
    {
        return $this->bookings()
            ->with(['dock', 'events.user'])
            ->findOrFail($bookingId);
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function hasVehicleWithPlate(string $licensePlate): bool
    {
        return $this->vehicles()->where('license_plate', Vehicle::normalizePlate($licensePlate))->exists();
    }

    /**
     * @return HasMany<Vehicle, $this>
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
