<?php

namespace App\Actions\ServiceRequests;

use App\Exceptions\RequestNotEditableException;
use App\Exceptions\ServiceRequestVersionConflictException;
use App\Models\ServiceRequest;
use Illuminate\Support\Facades\DB;

/**
 * Update the mutable fields of a non-terminal service request (title,
 * description, category). The client must echo the version it observed
 * (optimistic locking, PRD §27): a mismatch raises a 409 conflict and the
 * row's version is bumped on every successful write.
 */
final readonly class UpdateServiceRequest
{
    /**
     * @param  array{title?: string, description?: string, category_id?: string|null}  $data
     *
     * @throws RequestNotEditableException
     * @throws ServiceRequestVersionConflictException
     */
    public function handle(ServiceRequest $serviceRequest, array $data, ?int $expectedVersion = null): ServiceRequest
    {
        return DB::transaction(function () use ($serviceRequest, $data, $expectedVersion): ServiceRequest {
            $locked = ServiceRequest::query()
                ->whereKey($serviceRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($expectedVersion !== null && $expectedVersion !== (int) $locked->version) {
                throw new ServiceRequestVersionConflictException;
            }

            if (! $locked->isEditable()) {
                throw new RequestNotEditableException;
            }

            $locked->fill($data);
            $locked->version++;
            $locked->save();

            return $locked->fresh();
        });
    }
}
