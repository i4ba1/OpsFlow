<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SLA policies (PRD §13.2): the timezone and business calendar that govern
     * SLA math for a group of requests. Service requests and projects already
     * carry a nullable sla_policy_id column without a foreign key; the
     * constraint is added in a later migration that also backfills the column.
     */
    public function up(): void
    {
        Schema::create('sla_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->foreign('organization_id')
                ->references('id')->on('organizations')
                ->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('timezone', 64)->default('Asia/Singapore');
            $table->uuid('business_calendar_id')->nullable();
            $table->foreign('business_calendar_id')
                ->references('id')->on('business_calendars')
                ->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_policies');
    }
};
