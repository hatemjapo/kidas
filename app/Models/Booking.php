<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\DockUsage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['order_number', 'pallets', 'license_plate', 'driver_phone', 'carrier_name'])]
class Booking extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DockUsage::class,
            'status' => BookingStatus::class,
            'pallets' => 'integer',
            'plate_mismatch' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'arrived_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return Attribute<string, string>
     */
    protected function licensePlate(): Attribute
    {
        return Attribute::set(fn (string $value): string => Vehicle::normalizePlate($value));
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function arrivedPlate(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => $value === null ? null : Vehicle::normalizePlate($value));
    }

    public static function getBookingForWarehouse(int $bookingId): self
    {
        return self::query()->with(['dock', 'supplier'])->findOrFail($bookingId);
    }

    public function getSupplierUser(): ?User
    {
        return $this->supplier?->user;
    }

    public function hasAnUnverifiedPlateMismatch(): bool
    {
        return $this->plate_mismatch && $this->plate_verified_by === null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function plateVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'plate_verified_by');
    }

    /**
     * @return BelongsTo<Dock, $this>
     */
    public function dock(): BelongsTo
    {
        return $this->belongsTo(Dock::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<BookingEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(BookingEvent::class);
    }
}
