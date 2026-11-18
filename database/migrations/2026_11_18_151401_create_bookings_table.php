<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['inbound', 'outbound']);
            $table->foreignId('dock_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('carrier_name')->nullable();
            $table->string('order_number');
            $table->unsignedSmallInteger('pallets');
            $table->string('license_plate', 15);
            $table->string('driver_phone', 50);
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->enum('status', ['booked', 'arrived', 'in_progress', 'completed', 'cancelled', 'no_show'])
                ->default('booked');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('arrived_at')->nullable();
            $table->string('arrived_plate', 15)->nullable();
            $table->boolean('plate_mismatch')->default(false);
            $table->foreignId('plate_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['dock_id', 'starts_at']);
        });

        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_window_check CHECK (starts_at < ends_at)');

        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        DB::statement(<<<'SQL'
            ALTER TABLE bookings ADD CONSTRAINT bookings_no_overlap
            EXCLUDE USING gist (dock_id WITH =, tstzrange(starts_at, ends_at, '[)') WITH &&)
            WHERE (status IN ('booked', 'arrived', 'in_progress', 'completed'))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
