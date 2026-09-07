<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'invoice_number' => 'INV-'.now()->format('Y').'-'.str_pad(
                (string) fake()->unique()->numberBetween(1, 999999),
                6,
                '0',
                STR_PAD_LEFT,
            ),
            'customer_id' => Customer::factory(),
            'project_id' => null,
            'status' => InvoiceStatus::Draft,
            'currency' => 'SGD',
            'subtotal' => '0.00',
            'tax_amount' => '0.00',
            'total_amount' => '0.00',
            'issued_at' => null,
            'due_at' => null,
            'paid_at' => null,
            'notes' => null,
            'created_by' => null,
        ];
    }

    public function issued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Issued,
            'issued_at' => $attributes['issued_at'] ?? now(),
        ]);
    }
}
