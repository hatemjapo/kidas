<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docks', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('number')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();

        DB::table('docks')->insertOrIgnore(
            collect(range(1, 10))
                ->map(fn (int $number): array => [
                    'number' => $number,
                    'name' => "dock{$number}",
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all()
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('docks');
    }
};
