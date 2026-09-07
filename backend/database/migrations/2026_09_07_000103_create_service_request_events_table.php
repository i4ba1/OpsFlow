<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_request_events', function (Blueprint $table) {
            // Unified request timeline (PRD §66): every state transition,
            // assignment and priority change lands here as one ordered row, so
            // GET /service-requests/{id}/history serves the full history from a
            // single query.
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->foreign('organization_id')
                ->references('id')->on('organizations')
                ->cascadeOnDelete();
            $table->uuid('service_request_id');
            $table->foreign('service_request_id')
                ->references('id')->on('service_requests')
                ->cascadeOnDelete();
            $table->string('type', 30)->index();
            $table->uuid('actor_id')->nullable();
            $table->foreign('actor_id')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->string('from_value', 50)->nullable();
            $table->string('to_value', 50)->nullable();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['service_request_id', 'created_at']);
            $table->index(['organization_id', 'service_request_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_request_events');
    }
};
