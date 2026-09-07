<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * In-app notifications (PRD §18). Unlike Laravel's default notifications
     * migration this table is keyed by UUID and stores an actor plus the
     * affected resource (type + id), matching the PRD §18 payload shape.
     * Email/websocket delivery is wired in Phase 9; rows are persisted here by
     * the NotificationDispatcher.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->foreign('organization_id')
                ->references('id')->on('organizations')
                ->cascadeOnDelete();
            $table->uuid('user_id');
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->cascadeOnDelete();
            $table->string('type', 60)->index();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->uuid('actor_id')->nullable();
            $table->foreign('actor_id')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->string('resource_type', 60)->nullable();
            $table->string('resource_id', 60)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['organization_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
