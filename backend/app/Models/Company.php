<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory, HasUuid;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'companies';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email_domain',
        'subscription_status',
        'settings',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'subscription_status' => 'string',
        ];
    }

    /**
     * Get the users that belong to this company.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'tenant_id');
    }

    /**
     * Get the roles that belong to this company.
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class, 'tenant_id');
    }

    /**
     * Get the audit logs that belong to this company.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'tenant_id');
    }
}
