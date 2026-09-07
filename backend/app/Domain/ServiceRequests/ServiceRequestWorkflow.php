<?php

namespace App\Domain\ServiceRequests;

use App\Enums\ServiceRequestStatus;
use App\Exceptions\InvalidTransitionException;

/**
 * The service request state machine (PRD §12.2 transition matrix).
 *
 * Authorization is expressed as the permission string required to walk each
 * edge, which maps cleanly onto the RBAC permission model (request.transition,
 * request.resolve, request.close, request.reopen) instead of hard-coded roles.
 */
final class ServiceRequestWorkflow
{
    /**
     * Allowed transitions with the permission required to perform each one.
     *
     * @var array<string, array<string, string>>
     */
    private const TRANSITIONS = [
        ServiceRequestStatus::New->value => [
            ServiceRequestStatus::Triaged->value => 'request.transition',
            ServiceRequestStatus::Cancelled->value => 'request.close',
        ],
        ServiceRequestStatus::Triaged->value => [
            ServiceRequestStatus::InProgress->value => 'request.transition',
            ServiceRequestStatus::Cancelled->value => 'request.close',
        ],
        ServiceRequestStatus::InProgress->value => [
            ServiceRequestStatus::WaitingCustomer->value => 'request.transition',
            ServiceRequestStatus::WaitingInternal->value => 'request.transition',
            ServiceRequestStatus::Escalated->value => 'request.transition',
            ServiceRequestStatus::Resolved->value => 'request.resolve',
            ServiceRequestStatus::Cancelled->value => 'request.close',
        ],
        ServiceRequestStatus::WaitingCustomer->value => [
            ServiceRequestStatus::InProgress->value => 'request.transition',
        ],
        ServiceRequestStatus::WaitingInternal->value => [
            ServiceRequestStatus::InProgress->value => 'request.transition',
        ],
        ServiceRequestStatus::Escalated->value => [
            ServiceRequestStatus::InProgress->value => 'request.transition',
            ServiceRequestStatus::Resolved->value => 'request.resolve',
        ],
        ServiceRequestStatus::Resolved->value => [
            ServiceRequestStatus::Closed->value => 'request.close',
            ServiceRequestStatus::InProgress->value => 'request.reopen',
        ],
        ServiceRequestStatus::Closed->value => [
            ServiceRequestStatus::InProgress->value => 'request.reopen',
        ],
        ServiceRequestStatus::Cancelled->value => [],
    ];

    public function canTransition(ServiceRequestStatus $from, ServiceRequestStatus $to): bool
    {
        return isset(self::TRANSITIONS[$from->value][$to->value]);
    }

    /**
     * The permission required to walk a transition edge.
     */
    public function permissionFor(ServiceRequestStatus $from, ServiceRequestStatus $to): string
    {
        return self::TRANSITIONS[$from->value][$to->value] ?? 'request.transition';
    }

    /**
     * @return list<ServiceRequestStatus>
     */
    public function allowedTargets(ServiceRequestStatus $from): array
    {
        $targets = [];

        foreach (array_keys(self::TRANSITIONS[$from->value]) as $value) {
            $targets[] = ServiceRequestStatus::from($value);
        }

        return $targets;
    }

    /**
     * @throws InvalidTransitionException
     */
    public function assertCanTransition(ServiceRequestStatus $from, ServiceRequestStatus $to): void
    {
        if (! $this->canTransition($from, $to)) {
            throw new InvalidTransitionException(
                sprintf('Transition from %s to %s is not allowed.', $from->value, $to->value),
            );
        }
    }
}
