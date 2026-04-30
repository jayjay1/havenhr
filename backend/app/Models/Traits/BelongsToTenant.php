<?php

namespace App\Models\Traits;

use App\Models\Scopes\TenantScope;
use App\Services\TenantContext;

/**
 * Trait for models that belong to a tenant.
 *
 * Automatically applies the TenantScope global scope to filter queries
 * by tenant_id, and sets tenant_id on new records from the current
 * TenantContext if not already set.
 */
trait BelongsToTenant
{
    /**
     * Boot the BelongsToTenant trait.
     */
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $tenantContext = app(TenantContext::class);

                if ($tenantContext->hasTenant()) {
                    $model->tenant_id = $tenantContext->getTenantId();
                }
            }
        });
    }
}
