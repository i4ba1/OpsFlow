<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->foreign('organization_id')
                ->references('id')->on('organizations')
                ->cascadeOnDelete();
            $table->uuid('customer_id');
            $table->foreign('customer_id')
                ->references('id')->on('customers')
                ->cascadeOnDelete();
            $table->string('project_code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('DRAFT')->index();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->uuid('manager_id')->nullable();
            $table->foreign('manager_id')
                ->references('id')->on('users')
                ->nullOnDelete();
            // SLA policies land in Phase 5; the column is stored now without a
            // foreign key constraint so the FK can be added with the table.
            $table->uuid('sla_policy_id')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'project_code']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
