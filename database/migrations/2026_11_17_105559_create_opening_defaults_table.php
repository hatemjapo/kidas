<?php

use App\Enums\Weekday;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opening_defaults', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('weekday')->unique();
            $table->time('opens_at');
            $table->time('closes_at');
            $table->boolean('is_closed')->default(false);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE opening_defaults ADD CONSTRAINT opening_defaults_window_check CHECK (is_closed OR opens_at < closes_at)');

        $now = now();

        DB::table('opening_defaults')->insertOrIgnore(
            collect(Weekday::cases())
                ->map(fn (Weekday $weekday): array => [
                    'weekday' => $weekday->value,
                    'opens_at' => '06:00:00',
                    'closes_at' => '18:00:00',
                    'is_closed' => $weekday->isWeekend(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all()
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_defaults');
    }
};
