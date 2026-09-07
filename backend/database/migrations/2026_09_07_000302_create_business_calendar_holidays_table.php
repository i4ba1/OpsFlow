<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Named whole-day closures of a business calendar (PRD §13.3). Holidays are
     * matched in the calendar's own timezone.
     */
    public function up(): void
    {
        Schema::create('business_calendar_holidays', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('business_calendar_id');
            $table->foreign('business_calendar_id')
                ->references('id')->on('business_calendars')
                ->cascadeOnDelete();
            $table->string('name');
            $table->date('date');
            $table->timestamps();

            $table->unique(['business_calendar_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_calendar_holidays');
    }
};
