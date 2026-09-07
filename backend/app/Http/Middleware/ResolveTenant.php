<?php

namespace App\Http\Middleware;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveTenant
{
    public function __construct(private readonly TenantContext $tenants) {}

    /**
     * Resolve the tenant from the authenticated user's organization and bind
     * it into the container as the active TenantContext (PRD §9.2/§9.4).
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user !== null) {
            /** @var Organization|null $organization */
            $organization = $user->organization;

            if ($organization === null) {
                abort(403, 'Your account is not associated with an organization.');
            }

            if ($organization->status === OrganizationStatus::Suspended) {
                abort(403, 'This organization is suspended.');
            }

            $this->tenants->set($organization);
        }

        return $next($request);
    }
}
