<?php

namespace App\Models\Traits;

use Illuminate\Support\Str;

/**
 * Trait for models that use UUID v4 primary keys.
 *
 * Automatically generates a UUID when creating a new model instance.
 * Configures the model to use a non-incrementing string key type.
 */
trait HasUuid
{
    /**
     * Boot the UUID trait.
     */
    protected static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    /**
     * Indicates that the IDs are not auto-incrementing.
     */
    public function getIncrementing(): bool
    {
        return false;
    }

    /**
     * The type of the primary key ID.
     */
    public function getKeyType(): string
    {
        return 'string';
    }
}
