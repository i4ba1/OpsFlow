<?php

namespace App\Support\Tenancy;

use App\Models\Organization;
use RuntimeException;

final class TenantContext
{
    private ?Organization $organization = null;

    public function set(Organization $organization): void
    {
        $this->organization = $organization;
    }

    public function has(): bool
    {
        return $this->organization !== null;
    }

    public function id(): string
    {
        return $this->organization()->id;
    }

    public function organization(): Organization
    {
        if ($this->organization === null) {
            throw new RuntimeException('TenantContext has not been resolved for this request.');
        }

        return $this->organization;
    }
}
