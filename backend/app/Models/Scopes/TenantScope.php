<?php

namespace App\Models\Scopes;

use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global query scope that automatically filters queries by tenant_id.
 *
 * When a tenant context is active, all queries on models using this scope
 * will include a WHERE tenant_id = ? clause. When no tenant context is set,
 * queries proceed without filtering (for system-level operations).
 */
class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenantContext = app(TenantContext::class);

        if ($tenantContext->hasTenant()) {
            $builder->where($model->getTable() . '.tenant_id', $tenantContext->getTenantId());
        }
    }
}
