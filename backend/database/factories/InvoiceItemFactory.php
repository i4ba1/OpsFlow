<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    public function definition(): array
    {
        $unitPrice = (string) $this->faker->randomFloat(2, 10, 1000);
        $quantity = '1.00';

        return [
            'invoice_id' => Invoice::factory(),
            'description' => $this->faker->sentence(3),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'amount' => bcmul($quantity, $unitPrice, 2),
            'sort_order' => 0,
        ];
    }
}
