<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->foreign('organization_id')
                ->references('id')->on('organizations')
                ->cascadeOnDelete();
            $table->string('request_number');
            $table->uuid('customer_id');
            $table->foreign('customer_id')
                ->references('id')->on('customers')
                ->restrictOnDelete();
            $table->uuid('project_id')->nullable();
            $table->foreign('project_id')
                ->references('id')->on('projects')
                ->nullOnDelete();
            $table->uuid('requested_by');
            $table->foreign('requested_by')
                ->references('id')->on('users')
                ->restrictOnDelete();
            $table->uuid('assigned_to')->nullable();
            $table->foreign('assigned_to')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->string('title');
            $table->text('description');
            $table->uuid('category_id')->nullable();
            $table->foreign('category_id')
                ->references('id')->on('service_request_categories')
                ->nullOnDelete();
            $table->string('priority', 20)->default('MEDIUM')->index();
            $table->string('status', 20)->default('NEW')->index();
            // SLA policies arrive in Phase 5; the column is stored now without a
            // foreign key constraint so the FK can be added with the table.
            $table->uuid('sla_policy_id')->nullable();
            $table->timestamp('first_response_due_at')->nullable();
            $table->timestamp('resolution_due_at')->nullable();
            $table->timestamp('first_responded_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['organization_id', 'request_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'priority']);
            $table->index(['organization_id', 'assigned_to', 'status']);
            $table->index(['organization_id', 'customer_id']);
            // PRD §25 specifies a partial index here
            // (organization_id, resolution_due_at) WHERE resolved_at IS NULL;
            // SQLite cannot create partial indexes through the schema builder,
            // so the equivalent plain composite index is used for V1 and the
            // Postgres migration should swap in the partial variant.
            $table->index(['organization_id', 'resolution_due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
