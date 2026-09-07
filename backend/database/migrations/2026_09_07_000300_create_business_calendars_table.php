<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Business calendars (PRD §13.3): the working-week hours and holidays used
     * by the SLA calculator. SLA math runs in the calendar timezone while all
     * stored timestamps stay in UTC (§68-69).
     */
    public function up(): void
    {
        Schema::create('business_calendars', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->foreign('organization_id')
                ->references('id')->on('organizations')
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('timezone', 64)->default('Asia/Singapore');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_calendars');
    }
};
