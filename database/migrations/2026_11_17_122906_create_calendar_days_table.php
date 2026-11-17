<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_days', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->boolean('is_released')->default(false);
            $table->timestamp('released_at')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->time('opens_at');
            $table->time('closes_at');
            $table->time('early_start');
            $table->time('early_end');
            $table->time('late_start');
            $table->time('late_end');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE calendar_days ADD CONSTRAINT calendar_days_window_check CHECK (is_closed OR opens_at < closes_at)');
        DB::statement('ALTER TABLE calendar_days ADD CONSTRAINT calendar_days_early_shift_check CHECK (early_start < early_end)');
        DB::statement('ALTER TABLE calendar_days ADD CONSTRAINT calendar_days_late_shift_check CHECK (late_start < late_end)');
        DB::statement('ALTER TABLE calendar_days ADD CONSTRAINT calendar_days_shift_order_check CHECK (early_end <= late_start)');
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_days');
    }
};
