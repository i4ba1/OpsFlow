<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comments on service requests (PRD §14). PUBLIC comments are visible to
     * everyone who can see the request, INTERNAL comments only to staff with
     * the comment.internal permission. Rows are soft-deleted by their author.
     */
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->foreign('organization_id')
                ->references('id')->on('organizations')
                ->cascadeOnDelete();
            $table->uuid('service_request_id');
            $table->foreign('service_request_id')
                ->references('id')->on('service_requests')
                ->cascadeOnDelete();
            $table->uuid('author_id');
            $table->foreign('author_id')
                ->references('id')->on('users')
                ->restrictOnDelete();
            $table->text('body');
            $table->string('visibility', 20)->default('PUBLIC')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['service_request_id', 'created_at']);
            $table->index(['organization_id', 'visibility']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
