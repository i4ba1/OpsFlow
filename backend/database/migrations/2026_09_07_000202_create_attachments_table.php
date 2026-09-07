<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Attachments on service requests and comments (PRD §15). The approval_id
     * column is reserved for the Phase 6 approval module and intentionally has
     * no foreign key yet. Files are stored under storage_path
     * attachments/{uuid}/{uuid}.{ext} — never under the client filename — and
     * soft-deleted together with their storage object.
     */
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->foreign('organization_id')
                ->references('id')->on('organizations')
                ->cascadeOnDelete();
            $table->uuid('service_request_id')->nullable();
            $table->foreign('service_request_id')
                ->references('id')->on('service_requests')
                ->nullOnDelete();
            $table->uuid('comment_id')->nullable();
            $table->foreign('comment_id')
                ->references('id')->on('comments')
                ->nullOnDelete();
            // Approvals arrive in Phase 6 — column only, no FK constraint.
            $table->uuid('approval_id')->nullable();
            $table->uuid('uploaded_by');
            $table->foreign('uploaded_by')
                ->references('id')->on('users')
                ->restrictOnDelete();
            $table->string('original_name');
            $table->string('storage_disk', 32)->default('s3');
            $table->string('storage_path');
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('service_request_id');
            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
