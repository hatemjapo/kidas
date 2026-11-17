<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dock_day_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_day_id')->constrained()->cascadeOnDelete();
            $table->enum('shift', ['early', 'late']);
            $table->foreignId('dock_id')->constrained()->cascadeOnDelete();
            $table->enum('usage', ['inbound', 'outbound']);
            $table->timestamps();

            $table->unique(['calendar_day_id', 'shift', 'dock_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dock_day_usages');
    }
};
