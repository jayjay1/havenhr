<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScorecardCriterion extends Model
{
    use HasFactory, HasUuid;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'scorecard_criteria';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'scorecard_id',
        'question_text',
        'category',
        'sort_order',
        'rating',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'rating' => 'integer',
        ];
    }

    /**
     * Get the scorecard this criterion belongs to.
     */
    public function scorecard(): BelongsTo
    {
        return $this->belongsTo(Scorecard::class);
    }
}
