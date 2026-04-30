<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Resume extends Model
{
    use HasFactory, HasUuid;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'resumes';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'candidate_id',
        'title',
        'template_slug',
        'content',
        'is_complete',
        'public_link_token',
        'public_link_active',
        'show_contact_on_public',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => 'json',
            'is_complete' => 'boolean',
            'public_link_active' => 'boolean',
            'show_contact_on_public' => 'boolean',
        ];
    }

    /**
     * Get the candidate that owns this resume.
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /**
     * Get the versions for this resume.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ResumeVersion::class);
    }
}
