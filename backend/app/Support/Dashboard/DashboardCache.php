<?php

namespace App\Support\Dashboard;

/**
 * Cache keys for the Phase 8 dashboard payloads (PRD §67). One key per
 * endpoint per organization, refreshed every 60 seconds; clients can force a
 * recompute with ?refresh=1. Keys are centralized here so the controller and
 * the query layer (used by tests) stay in sync.
 */
final class DashboardCache
{
    public const TTL_SECONDS = 60;

    public const OVERVIEW = 'dashboard:overview:';

    public const REQUEST_VOLUME = 'dashboard:volume:';

    public const SLA = 'dashboard:sla:';

    public const TEAM_PERFORMANCE = 'dashboard:team:';

    /**
     * The fully namespaced cache key of a payload for an organization.
     */
    public static function key(string $payload, string $organizationId): string
    {
        return $payload.$organizationId;
    }

    /**
     * @return list<string>
     */
    public static function keys(string $organizationId): array
    {
        return [
            self::key(self::OVERVIEW, $organizationId),
            self::key(self::REQUEST_VOLUME, $organizationId),
            self::key(self::SLA, $organizationId),
            self::key(self::TEAM_PERFORMANCE, $organizationId),
        ];
    }
}
