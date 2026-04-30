<?php

namespace App\Services;

/**
 * Singleton service that holds the current tenant context.
 *
 * Used by TenantScope to automatically filter queries by tenant_id,
 * and by BelongsToTenant to auto-set tenant_id on new records.
 */
class TenantContext
{
    /**
     * The current tenant ID.
     */
    protected ?string $tenantId = null;

    /**
     * Set the current tenant ID.
     */
    public function setTenantId(string $id): void
    {
        $this->tenantId = $id;
    }

    /**
     * Get the current tenant ID.
     */
    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    /**
     * Check if a tenant ID is currently set.
     */
    public function hasTenant(): bool
    {
        return $this->tenantId !== null;
    }
}
