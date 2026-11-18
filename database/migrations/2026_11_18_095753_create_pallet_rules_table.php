<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pallet_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('max_pallets')->unique();
            $table->unsignedSmallInteger('duration_minutes');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE pallet_rules ADD CONSTRAINT pallet_rules_duration_check CHECK (duration_minutes > 0 AND duration_minutes % 15 = 0)');

        $now = now();

        DB::table('pallet_rules')->insertOrIgnore(
            collect([10, 20, 30, 40, 50, 60, 70])
                ->map(fn (int $maxPallets): array => [
                    'max_pallets' => $maxPallets,
                    'duration_minutes' => ($maxPallets / 10) * 15,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all()
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('pallet_rules');
    }
};
