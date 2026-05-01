<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\HasUuid;
use Database\Factories\JobPostingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobPosting extends Model
{
    /** @use HasFactory<JobPostingFactory> */
    use BelongsToTenant, HasFactory, HasUuid, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'job_postings';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'slug',
        'description',
        'location',
        'employment_type',
        'department',
        'salary_min',
        'salary_max',
        'salary_currency',
        'requirements',
        'benefits',
        'remote_status',
        'status',
        'published_at',
        'closed_at',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
            'salary_min' => 'integer',
            'salary_max' => 'integer',
        ];
    }

    /**
     * Get the company (tenant) that owns this job posting.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'tenant_id');
    }

    /**
     * Get the user who created this job posting.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the pipeline stages for this job posting.
     */
    public function pipelineStages(): HasMany
    {
        return $this->hasMany(PipelineStage::class);
    }

    /**
     * Get the job applications for this job posting.
     */
    public function jobApplications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }
}
