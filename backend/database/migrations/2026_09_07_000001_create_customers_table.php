<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->foreign('organization_id')
                ->references('id')->on('organizations')
                ->cascadeOnDelete();
            $table->string('customer_code');
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->char('country_code', 2)->default('SG');
            $table->string('status', 20)->default('ACTIVE')->index();
            $table->jsonb('metadata')->nullable();
            $table->uuid('created_by')->nullable();
            $table->foreign('created_by')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->unique(['organization_id', 'customer_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
