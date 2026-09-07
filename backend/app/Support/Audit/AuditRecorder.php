<?php

namespace App\Support\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Append-only audit recorder (PRD §19). Every sensitive mutation produces one
 * row; sensitive values are redacted before they are persisted and passwords,
 * tokens and reset secrets are never logged.
 */
final class AuditRecorder
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>|null  $metadata
     */
    public function record(
        ?User $actor,
        string $action,
        Model|string $entity,
        ?string $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
        ?Request $request = null,
    ): AuditLog {
        [$type, $id, $organizationId] = $this->resolveEntity($entity, $entityId);

        $organizationId ??= $actor?->organization_id;

        abort_if($organizationId === null, 500, 'An audit entry requires an organization.');

        $attributes = [
            'organization_id' => $organizationId,
            'actor_id' => $actor?->id,
            'action' => $action,
            'entity_type' => $type,
            'entity_id' => $id,
            'old_values' => $oldValues === null ? null : $this->redact($oldValues),
            'new_values' => $newValues === null ? null : $this->redact($newValues),
            'metadata' => $metadata,
            'ip_address' => $request?->ip(),
            'user_agent' => $request !== null ? Str::limit((string) $request->userAgent(), 500) : null,
        ];

        if ($request !== null && $request->attributes->has('request_id')) {
            $attributes['request_id'] = (string) $request->attributes->get('request_id');
        }

        return AuditLog::query()->create($attributes);
    }

    /**
     * Sensitive fields must never reach the log (PRD §19 / §45).
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function redact(array $values): array
    {
        $sensitive = ['password', 'token', 'secret', 'authorization', 'api_key', 'api_key_id'];

        foreach ($values as $key => $value) {
            foreach ($sensitive as $needle) {
                if (str_contains(strtolower((string) $key), $needle)) {
                    $values[$key] = '[REDACTED]';
                    break;
                }
            }
        }

        return $values;
    }

    /**
     * @return array{0: string, 1: string|null, 2: string|null}
     */
    private function resolveEntity(Model|string $entity, ?string $entityId): array
    {
        if ($entity instanceof Model) {
            return [
                Str::snake(class_basename($entity)),
                (string) $entity->getKey(),
                isset($entity->organization_id) ? (string) $entity->organization_id : null,
            ];
        }

        return [$entity, $entityId, null];
    }
}
