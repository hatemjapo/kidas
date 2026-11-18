<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('from_status', ['booked', 'arrived', 'in_progress', 'completed', 'cancelled', 'no_show'])
                ->nullable();
            $table->enum('to_status', ['booked', 'arrived', 'in_progress', 'completed', 'cancelled', 'no_show']);
            $table->text('note')->nullable();
            $table->timestampTz('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_events');
    }
};
