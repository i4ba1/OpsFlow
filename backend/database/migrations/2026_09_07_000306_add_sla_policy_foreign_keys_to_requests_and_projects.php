<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The sla_policy_id columns on service_requests and projects were created
     * in Phase 3/4 without foreign keys ("the FK can be added with the table"
     * in Phase 5). Both org tables share the sla_policies FK with ON DELETE
     * SET NULL so requests keep their numbers and history when a policy is
     * removed.
     */
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->foreign('sla_policy_id')
                ->references('id')->on('sla_policies')
                ->nullOnDelete();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreign('sla_policy_id')
                ->references('id')->on('sla_policies')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropForeign(['sla_policy_id']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['sla_policy_id']);
        });
    }
};
