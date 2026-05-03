<?php

namespace App\Models;

use App\Models\Traits\HasUuid;
use Database\Factories\CandidateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Candidate extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<CandidateFactory> */
    use HasFactory, HasUuid, Notifiable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'candidates';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password_hash',
        'phone',
        'location',
        'linkedin_url',
        'portfolio_url',
        'is_active',
        'notification_preferences',
        'email_verified_at',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password_hash',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'notification_preferences' => 'array',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Get the password for authentication.
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     */
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array<string, mixed>
     */
    public function getJWTCustomClaims(): array
    {
        return [
            'role' => 'candidate',
        ];
    }

    /**
     * Get the refresh tokens for this candidate.
     */
    public function refreshTokens(): HasMany
    {
        return $this->hasMany(CandidateRefreshToken::class);
    }

    /**
     * Get the work history entries for this candidate.
     */
    public function workHistories(): HasMany
    {
        return $this->hasMany(CandidateWorkHistory::class);
    }

    /**
     * Get the education entries for this candidate.
     */
    public function educations(): HasMany
    {
        return $this->hasMany(CandidateEducation::class);
    }

    /**
     * Get the skills for this candidate.
     */
    public function skills(): HasMany
    {
        return $this->hasMany(CandidateSkill::class);
    }

    /**
     * Get the resumes for this candidate.
     */
    public function resumes(): HasMany
    {
        return $this->hasMany(Resume::class);
    }

    /**
     * Get the AI jobs for this candidate.
     */
    public function aiJobs(): HasMany
    {
        return $this->hasMany(AIJob::class);
    }

    /**
     * Get the job applications for this candidate.
     */
    public function jobApplications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }
}
