<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Database\Factories\PipelineStageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PipelineStage extends Model
{
    /** @use HasFactory<PipelineStageFactory> */
    use HasFactory, HasUuid;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pipeline_stages';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'job_posting_id',
        'name',
        'color',
        'sort_order',
    ];

    /**
     * Get the job posting that this stage belongs to.
     */
    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    /**
     * Get the job applications currently in this stage.
     */
    public function jobApplications(): HasMany
    {
        return $this->hasMany(JobApplication::class, 'pipeline_stage_id');
    }
}
