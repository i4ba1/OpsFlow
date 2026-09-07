<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\InvoiceItem;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $invoice_number
 * @property string $customer_id
 * @property string|null $project_id
 * @property InvoiceStatus $status
 * @property string $currency
 * @property string $subtotal
 * @property string $tax_amount
 * @property string $total_amount
 * @property Carbon|null $issued_at
 * @property Carbon|null $due_at
 * @property Carbon|null $paid_at
 * @property string|null $notes
 * @property Customer $customer
 * @property Project|null $project
 * @property User|null $createdBy
 * @property Collection<int, InvoiceItem> $items
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'customer_id' => $this->customer_id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'project_id' => $this->project_id,
            'project' => new ProjectResource($this->whenLoaded('project')),
            'status' => $this->status,
            'currency' => $this->currency,
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'issued_at' => $this->issued_at,
            'due_at' => $this->due_at?->toDateString(),
            'paid_at' => $this->paid_at,
            'notes' => $this->notes,
            'created_by' => $this->createdBy?->id,
            'items' => InvoiceItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
