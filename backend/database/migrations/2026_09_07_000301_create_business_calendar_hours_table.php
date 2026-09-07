<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Weekly working intervals of a business calendar (PRD §13.3). One row per
     * day of week (0 = Sunday .. 6 = Saturday); a day without a row is closed.
     */
    public function up(): void
    {
        Schema::create('business_calendar_hours', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_calendar_id');
            $table->foreign('business_calendar_id')
                ->references('id')->on('business_calendars')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('opens_at');
            $table->time('closes_at');
            $table->timestamps();

            $table->unique(['business_calendar_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_calendar_hours');
    }
};
