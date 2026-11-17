<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_defaults', function (Blueprint $table) {
            $table->id();
            $table->enum('shift', ['early', 'late'])->unique();
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE shift_defaults ADD CONSTRAINT shift_defaults_window_check CHECK (starts_at < ends_at)');

        $now = now();

        DB::table('shift_defaults')->insertOrIgnore([
            [
                'shift' => 'early',
                'starts_at' => '06:00:00',
                'ends_at' => '14:00:00',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'shift' => 'late',
                'starts_at' => '14:00:00',
                'ends_at' => '18:00:00',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_defaults');
    }
};
