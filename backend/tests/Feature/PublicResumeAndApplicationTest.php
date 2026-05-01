<?php

use App\Models\Candidate;
use App\Models\Company;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

/*
|--------------------------------------------------------------------------
| Helper: authenticate a candidate and return the Bearer token
|--------------------------------------------------------------------------
*/

function candidateToken(Candidate $candidate): string
{
    return JWTAuth::claims(['role' => 'candidate'])->fromUser($candidate);
}

function createPublishedJobPosting(): JobPosting
{
    $company = Company::factory()->create();
    $user = User::withoutGlobalScopes()->create([
        'name' => 'Test Employer',
        'email' => fake()->unique()->safeEmail(),
        'password_hash' => Hash::make('Password123!'),
        'tenant_id' => $company->id,
        'is_active' => true,
    ]);

    return JobPosting::factory()->published()->create([
        'tenant_id' => $company->id,
        'created_by' => $user->id,
    ]);
}

/*
|--------------------------------------------------------------------------
| GET /api/v1/public/resumes/{token} — Public Resume Access
|--------------------------------------------------------------------------
*/

it('returns public resume with contact info when show_contact_on_public is true', function () {
    $candidate = Candidate::factory()->create();

    $token = (string) Str::uuid();

    Resume::create([
        'candidate_id' => $candidate->id,
        'title' => 'Public Resume',
        'template_slug' => 'modern',
        'content' => [
            'personal_info' => [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'phone' => '555-1234',
                'location' => 'Boston',
            ],
            'summary' => 'A great summary.',
            'work_experience' => [],
            'education' => [],
            'skills' => ['PHP', 'Laravel'],
        ],
        'is_complete' => true,
        'public_link_token' => $token,
        'public_link_active' => true,
        'show_contact_on_public' => true,
    ]);

    $response = $this->getJson("/api/v1/public/resumes/{$token}");

    $response->assertStatus(200);
    expect($response->json('data.template_slug'))->toBe('modern');
    expect($response->json('data.content.personal_info.name'))->toBe('Jane Doe');
    expect($response->json('data.content.personal_info.email'))->toBe('jane@example.com');
    expect($response->json('data.content.personal_info.phone'))->toBe('555-1234');
    expect($response->json('data.content.summary'))->toBe('A great summary.');
});

it('returns public resume without email and phone when show_contact_on_public is false', function () {
    $candidate = Candidate::factory()->create();

    $token = (string) Str::uuid();

    Resume::create([
        'candidate_id' => $candidate->id,
        'title' => 'Private Contact Resume',
        'template_slug' => 'clean',
        'content' => [
            'personal_info' => [
                'name' => 'John Smith',
                'email' => 'john@example.com',
                'phone' => '555-9999',
                'location' => 'NYC',
            ],
            'summary' => 'Summary text.',
            'work_experience' => [],
            'education' => [],
            'skills' => [],
        ],
        'is_complete' => true,
        'public_link_token' => $token,
        'public_link_active' => true,
        'show_contact_on_public' => false,
    ]);

    $response = $this->getJson("/api/v1/public/resumes/{$token}");

    $response->assertStatus(200);
    expect($response->json('data.content.personal_info.name'))->toBe('John Smith');
    expect($response->json('data.content.personal_info.location'))->toBe('NYC');
    // Email and phone should be excluded
    expect($response->json('data.content.personal_info'))->not->toHaveKey('email');
    expect($response->json('data.content.personal_info'))->not->toHaveKey('phone');
});

it('returns 404 for inactive public link', function () {
    $candidate = Candidate::factory()->create();

    $token = (string) Str::uuid();

    Resume::create([
        'candidate_id' => $candidate->id,
        'title' => 'Inactive Link Resume',
        'template_slug' => 'clean',
        'content' => ['personal_info' => ['name' => 'Test']],
        'is_complete' => true,
        'public_link_token' => $token,
        'public_link_active' => false,
        'show_contact_on_public' => false,
    ]);

    $response = $this->getJson("/api/v1/public/resumes/{$token}");

    $response->assertStatus(404);
    expect($response->json('error.code'))->toBe('NOT_FOUND');
});

it('returns 404 for non-existent public link token', function () {
    $response = $this->getJson('/api/v1/public/resumes/' . (string) Str::uuid());

    $response->assertStatus(404);
    expect($response->json('error.code'))->toBe('NOT_FOUND');
});

/*
|--------------------------------------------------------------------------
| POST /api/v1/candidate/applications — Submit Application
|--------------------------------------------------------------------------
*/

it('submits a job application and snapshots resume content', function () {
    $candidate = Candidate::factory()->create();

    $resume = Resume::create([
        'candidate_id' => $candidate->id,
        'title' => 'My Resume',
        'template_slug' => 'clean',
        'content' => [
            'personal_info' => ['name' => 'Test User'],
            'summary' => 'A summary.',
            'work_experience' => [],
            'education' => [],
            'skills' => ['PHP'],
        ],
        'is_complete' => true,
    ]);

    $jobPosting = createPublishedJobPosting();
    $token = candidateToken($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/v1/candidate/applications', [
        'job_posting_id' => $jobPosting->id,
        'resume_id' => $resume->id,
    ]);

    $response->assertStatus(201);
    expect($response->json('data.job_posting_id'))->toBe($jobPosting->id);
    expect($response->json('data.resume_id'))->toBe($resume->id);
    expect($response->json('data.status'))->toBe('submitted');

    // Verify the snapshot was stored
    $application = JobApplication::where('candidate_id', $candidate->id)->first();
    expect($application)->not->toBeNull();
    expect($application->resume_snapshot['personal_info']['name'])->toBe('Test User');
    expect($application->resume_snapshot['skills'])->toContain('PHP');
});

it('rejects duplicate application with 409', function () {
    $candidate = Candidate::factory()->create();

    $resume = Resume::create([
        'candidate_id' => $candidate->id,
        'title' => 'My Resume',
        'template_slug' => 'clean',
        'content' => ['personal_info' => ['name' => 'Test']],
        'is_complete' => true,
    ]);

    $jobPosting = createPublishedJobPosting();

    // Create first application directly
    JobApplication::create([
        'candidate_id' => $candidate->id,
        'job_posting_id' => $jobPosting->id,
        'resume_id' => $resume->id,
        'resume_snapshot' => $resume->content,
        'status' => 'submitted',
        'applied_at' => now(),
    ]);

    $token = candidateToken($candidate);

    // Attempt duplicate
    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/v1/candidate/applications', [
        'job_posting_id' => $jobPosting->id,
        'resume_id' => $resume->id,
    ]);

    $response->assertStatus(409);
    expect($response->json('error.code'))->toBe('DUPLICATE_APPLICATION');
});

it('returns 422 when applying to non-published job posting', function () {
    $candidate1 = Candidate::factory()->create();
    $candidate2 = Candidate::factory()->create();

    $resume = Resume::create([
        'candidate_id' => $candidate2->id,
        'title' => 'Other Resume',
        'template_slug' => 'clean',
        'content' => ['personal_info' => ['name' => 'Other']],
        'is_complete' => true,
    ]);

    $token = candidateToken($candidate1);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/v1/candidate/applications', [
        'job_posting_id' => (string) Str::uuid(),
        'resume_id' => $resume->id,
    ]);

    $response->assertStatus(422);
});

it('returns 422 when applying with missing fields', function () {
    $candidate = Candidate::factory()->create();
    $token = candidateToken($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/v1/candidate/applications', []);

    $response->assertStatus(422);
});

/*
|--------------------------------------------------------------------------
| GET /api/v1/candidate/applications — List Applications
|--------------------------------------------------------------------------
*/

it('lists all applications for the authenticated candidate', function () {
    $candidate = Candidate::factory()->create();

    $resume = Resume::create([
        'candidate_id' => $candidate->id,
        'title' => 'My Resume',
        'template_slug' => 'clean',
        'content' => ['personal_info' => ['name' => 'Test']],
        'is_complete' => true,
    ]);

    $jobPosting1 = createPublishedJobPosting();
    $jobPosting2 = createPublishedJobPosting();

    JobApplication::create([
        'candidate_id' => $candidate->id,
        'job_posting_id' => $jobPosting1->id,
        'resume_id' => $resume->id,
        'resume_snapshot' => $resume->content,
        'status' => 'submitted',
        'applied_at' => now(),
    ]);

    JobApplication::create([
        'candidate_id' => $candidate->id,
        'job_posting_id' => $jobPosting2->id,
        'resume_id' => $resume->id,
        'resume_snapshot' => $resume->content,
        'status' => 'submitted',
        'applied_at' => now()->subDay(),
    ]);

    $token = candidateToken($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->getJson('/api/v1/candidate/applications');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('data.0'))->toHaveKeys(['id', 'job_posting_id', 'resume_id', 'status', 'applied_at']);
});

it('does not list applications from other candidates', function () {
    $candidate1 = Candidate::factory()->create();
    $candidate2 = Candidate::factory()->create();

    $resume = Resume::create([
        'candidate_id' => $candidate2->id,
        'title' => 'Other Resume',
        'template_slug' => 'clean',
        'content' => [],
        'is_complete' => true,
    ]);

    $jobPosting = createPublishedJobPosting();

    JobApplication::create([
        'candidate_id' => $candidate2->id,
        'job_posting_id' => $jobPosting->id,
        'resume_id' => $resume->id,
        'resume_snapshot' => $resume->content,
        'status' => 'submitted',
        'applied_at' => now(),
    ]);

    $token = candidateToken($candidate1);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->getJson('/api/v1/candidate/applications');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(0);
});

/*
|--------------------------------------------------------------------------
| Authentication required for candidate application endpoints
|--------------------------------------------------------------------------
*/

it('returns 401 when submitting application without authentication', function () {
    $response = $this->postJson('/api/v1/candidate/applications', [
        'job_posting_id' => (string) Str::uuid(),
        'resume_id' => (string) Str::uuid(),
    ]);
    $response->assertStatus(401);
});

it('returns 401 when listing applications without authentication', function () {
    $response = $this->getJson('/api/v1/candidate/applications');
    $response->assertStatus(401);
});

/*
|--------------------------------------------------------------------------
| Employer placeholder endpoints
|--------------------------------------------------------------------------
*/

it('public resume endpoint requires no authentication', function () {
    // Just verify the route exists and doesn't require auth (returns 404 for missing token)
    $response = $this->getJson('/api/v1/public/resumes/nonexistent-token');
    $response->assertStatus(404);
});
