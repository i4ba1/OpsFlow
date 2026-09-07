<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->foreign('organization_id')
                ->references('id')->on('organizations')
                ->cascadeOnDelete();
            $table->string('invoice_number');
            $table->uuid('customer_id');
            $table->foreign('customer_id')
                ->references('id')->on('customers')
                ->restrictOnDelete();
            $table->uuid('project_id')->nullable();
            $table->foreign('project_id')
                ->references('id')->on('projects')
                ->nullOnDelete();
            $table->string('status', 20)->default('DRAFT')->index();
            $table->char('currency', 3)->default('SGD');
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            // V1 has no tax engine (PRD §17): tax_amount stays 0 and the total
            // is always subtotal + tax_amount.
            $table->timestamp('issued_at')->nullable();
            $table->date('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->foreign('created_by')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'invoice_number']);
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'customer_id']);
            $table->index(['organization_id', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
