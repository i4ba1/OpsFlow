<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Approvals on service requests (PRD §16). An approval asks an internal
     * decision-maker (assigned_to) to accept or reject a proposed course of
     * action — a customer acceptance sign-off, a quotation, a change request
     * or an exception — before the underlying work proceeds. Decisions are
     * immutable once made; pending approvals past their due date are expired
     * by the ExpirePendingApprovals job (PRD §33).
     */
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->foreign('organization_id')
                ->references('id')->on('organizations')
                ->cascadeOnDelete();
            $table->uuid('service_request_id');
            $table->foreign('service_request_id')
                ->references('id')->on('service_requests')
                ->cascadeOnDelete();
            $table->uuid('requested_by');
            $table->foreign('requested_by')
                ->references('id')->on('users')
                ->restrictOnDelete();
            $table->uuid('assigned_to');
            $table->foreign('assigned_to')
                ->references('id')->on('users')
                ->restrictOnDelete();
            $table->string('type', 40);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('PENDING')->index();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->text('response_note')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['service_request_id', 'status']);
            $table->index(['organization_id', 'service_request_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
