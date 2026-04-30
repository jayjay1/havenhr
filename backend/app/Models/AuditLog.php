<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AuditLog extends Model
{
    use BelongsToTenant, HasFactory, HasUuid;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'audit_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'action',
        'resource_type',
        'resource_id',
        'previous_state',
        'new_state',
        'ip_address',
        'user_agent',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'previous_state' => 'array',
            'new_state' => 'array',
        ];
    }

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The name of the "created at" column.
     *
     * @var string|null
     */
    const CREATED_AT = 'created_at';

    /**
     * The name of the "updated at" column.
     *
     * @var string|null
     */
    const UPDATED_AT = null;

    /**
     * Save the model to the database.
     *
     * Audit logs are append-only. Updates are not allowed.
     *
     * @param  array<string, mixed>  $options
     * @return bool
     *
     * @throws \LogicException
     */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('Audit logs are append-only and cannot be updated.');
        }

        return parent::save($options);
    }

    /**
     * Delete the model from the database.
     *
     * Audit logs are append-only. Deletes are not allowed.
     *
     * @return never
     *
     * @throws \LogicException
     */
    public function delete(): never
    {
        throw new LogicException('Audit logs are append-only and cannot be deleted.');
    }

    /**
     * Get the company (tenant) that owns this audit log.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'tenant_id');
    }

    /**
     * Get the user that performed this action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
