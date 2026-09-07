<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_sequences', function (Blueprint $table) {
            // One row per organization and calendar year — the per-organization
            // / year counter backing the REQ-{year}-{000001} request numbers
            // (PRD §24.3). Rows are locked for update while the next number is
            // allocated so parallel creates cannot duplicate a number.
            $table->uuid('organization_id');
            $table->foreign('organization_id')
                ->references('id')->on('organizations')
                ->cascadeOnDelete();
            $table->smallInteger('year');
            $table->integer('last_value')->default(0);

            $table->primary(['organization_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_sequences');
    }
};
