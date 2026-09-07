<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SLA monitoring events (PRD §13.5/§13.6). One row is written each time a
     * request crosses the AT_RISK or BREACHED threshold and when an SLA is MET
     * on resolution. dedupe_key = "{service_request_id}:{type}" is unique so
     * the per-minute monitoring job can never notify twice for the same
     * threshold of the same request.
     */
    public function up(): void
    {
        Schema::create('sla_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->foreign('organization_id')
                ->references('id')->on('organizations')
                ->cascadeOnDelete();
            $table->uuid('service_request_id');
            $table->foreign('service_request_id')
                ->references('id')->on('service_requests')
                ->cascadeOnDelete();
            $table->uuid('sla_policy_id')->nullable();
            $table->foreign('sla_policy_id')
                ->references('id')->on('sla_policies')
                ->nullOnDelete();
            $table->string('type', 30);
            $table->string('priority', 20)->nullable();
            $table->smallInteger('threshold_percent')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('occurred_at');
            $table->json('metadata')->nullable();
            $table->string('dedupe_key', 100);
            $table->timestamps();

            $table->unique('dedupe_key');
            $table->index(['service_request_id', 'type']);
            $table->index(['organization_id', 'type']);
            $table->index(['organization_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sla_events');
    }
};
