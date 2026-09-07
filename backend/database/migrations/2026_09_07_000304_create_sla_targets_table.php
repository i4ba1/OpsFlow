<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-priority SLA targets (PRD §13.2): the business minutes allowed for a
     * first response and for full resolution, plus the percentage of the
     * resolution budget at which the request turns AT_RISK.
     */
    public function up(): void
    {
        Schema::create('sla_targets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sla_policy_id');
            $table->foreign('sla_policy_id')
                ->references('id')->on('sla_policies')
                ->cascadeOnDelete();
            $table->string('priority', 20);
            $table->integer('first_response_minutes');
            $table->integer('resolution_minutes');
            $table->smallInteger('warning_threshold_percent')->default(75);
            $table->timestamps();

            $table->unique(['sla_policy_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_targets');
    }
};
