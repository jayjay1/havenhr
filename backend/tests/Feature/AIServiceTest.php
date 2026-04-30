<?php

use App\Contracts\OpenAIServiceInterface;
use App\Jobs\ProcessAIJob;
use App\Models\AIJob;
use App\Models\Candidate;
use App\Services\MockOpenAIService;
use Illuminate\Support\Facades\Queue;
use Tymon\JWTAuth\Facades\JWTAuth;

/*
|--------------------------------------------------------------------------
| Helper: authenticate a candidate and return the Bearer token
|--------------------------------------------------------------------------
*/

function aiAuthCandidate(Candidate $candidate): string
{
    $customClaims = ['role' => 'candidate'];

    return JWTAuth::claims($customClaims)->fromUser($candidate);
}

/*
|--------------------------------------------------------------------------
| POST /api/v1/candidate/ai/summary — Generate Summary
|--------------------------------------------------------------------------
*/

it('creates a summary AI job and returns 202', function () {
    Queue::fake();

    $candidate = Candidate::factory()->create();
    $token = aiAuthCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/v1/candidate/ai/summary', [
        'job_title' => 'Software Engineer',
        'years_experience' => 5,
    ]);

    $response->assertStatus(202)
        ->assertJsonStructure([
            'data' => ['job_id', 'status'],
        ]);

    expect($response->json('data.status'))->toBe('pending');

    // Verify AI job was created in database
    $jobId = $response->json('data.job_id');
    $aiJob = AIJob::find($jobId);
    expect($aiJob)->not->toBeNull();
    expect($aiJob->job_type)->toBe('summary');
    expect($aiJob->status)->toBe('pending');
    expect($aiJob->candidate_id)->toBe($candidate->id);

    // Verify job was dispatched to queue
    Queue::assertPushed(ProcessAIJob::class);
});

it('creates a summary AI job with optional work_history', function () {
    Queue::fake();

    $candidate = Candidate::factory()->create();
    $token = aiAuthCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/v1/candidate/ai/summary', [
        'job_title' => 'Product Manager',
        'years_experience' => 8,
        'work_history' => ['Led product teams at multiple startups.', 'Managed a team of 10 engineers.'],
    ]);

    $response->assertStatus(202);
    $jobId = $response->json('data.job_id');
    $aiJob = AIJob::find($jobId);
    expect($aiJob->input_data['work_history'])->toBe(['Led product teams at multiple startups.', 'Managed a team of 10 engineers.']);
});

/*
|--------------------------------------------------------------------------
| POST /api/v1/candidate/ai/bullets — Generate Bullets
|--------------------------------------------------------------------------
*/

it('creates a bullets AI job and returns 202', function () {
    Queue::fake();

    $candidate = Candidate::factory()->create();
    $token = aiAuthCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/v1/candidate/ai/bullets', [
        'job_title' => 'Senior Developer',
        'company_name' => 'Acme Corp',
        'description' => 'Built and maintained web applications.',
    ]);

    $response->assertStatus(202);
    expect($response->json('data.status'))->toBe('pending');

    $aiJob = AIJob::find($response->json('data.job_id'));
    expect($aiJob->job_type)->toBe('bullets');
});

/*
|--------------------------------------------------------------------------
| POST /api/v1/candidate/ai/skills — Suggest Skills
|--------------------------------------------------------------------------
*/

it('creates a skills AI job and returns 202', function () {
    Queue::fake();

    $candidate = Candidate::factory()->create();
    $token = aiAuthCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/v1/candidate/ai/skills', [
        'job_title' => 'Data Scientist',
        'industry' => 'Technology',
        'existing_skills' => ['Python', 'SQL'],
    ]);

    $response->assertStatus(202);
    expect($response->json('data.status'))->toBe('pending');

    $aiJob = AIJob::find($response->json('data.job_id'));
    expect($aiJob->job_type)->toBe('skills');
    expect($aiJob->input_data['existing_skills'])->toBe(['Python', 'SQL']);
});

it('creates a skills AI job with only required fields', function () {
    Queue::fake();

    $candidate = Candidate::factory()->create();
    $token = aiAuthCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/v1/candidate/ai/skills', [
        'job_title' => 'Designer',
    ]);

    $response->assertStatus(202);
});

/*
|--------------------------------------------------------------------------
| POST /api/v1/candidate/ai/ats-optimize — ATS Optimization
|--------------------------------------------------------------------------
*/

it('creates an ats_optimize AI job and returns 202', function () {
    Queue::fake();

    $candidate = Candidate::factory()->create();
    $token = aiAuthCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/v1/candidate/ai/ats-optimize', [
        'job_description' => 'We are looking for a senior engineer with experience in React and Node.js.',
        'resume_content' => [
            'summary' => 'Experienced developer with 5 years of web development.',
            'skills' => ['JavaScript', 'HTML', 'CSS'],
        ],
    ]);

    $response->assertStatus(202);
    expect($response->json('data.status'))->toBe('pending');

    $aiJob = AIJob::find($response->json('data.job_id'));
    expect($aiJob->job_type)->toBe('ats_optimize');
});

/*
|--------------------------------------------------------------------------
| POST /api/v1/candidate/ai/improve — Improve Text
|--------------------------------------------------------------------------
*/

it('creates an improve AI job and returns 202', function () {
    Queue::fake();

    $candidate = Candidate::factory()->create();
    $token = aiAuthCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/v1/candidate/ai/improve', [
        'text' => 'I did stuff at my job and it was good.',
    ]);

    $response->assertStatus(202);
    expect($response->json('data.status'))->toBe('pending');

    $aiJob = AIJob::find($response->json('data.job_id'));
    expect($aiJob->job_type)->toBe('improve');
});

/*
|--------------------------------------------------------------------------
| Validation — Missing required fields
|--------------------------------------------------------------------------
*/

it('returns 422 for summary with missing job_title', function () {
    $candidate = Candidate::factory()->create();
    $token = aiAuthCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/v1/candidate/ai/summary', [
        'years_experience' => 5,
    ]);

    $response->assertStatus(422);
});

it('returns 422 for bullets with missing description', function () {
    $candidate = Candidate::factory()->create();
    $token = aiAuthCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/v1/candidate/ai/bullets', [
        'job_title' => 'Engineer',
        'company_name' => 'Acme',
    ]);

    $response->assertStatus(422);
});

it('returns 422 for improve with missing text', function () {
    $candidate = Candidate::factory()->create();
    $token = aiAuthCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/v1/candidate/ai/improve', []);

    $response->assertStatus(422);
});

/*
|--------------------------------------------------------------------------
| Validation — Max input length (5000 chars)
|--------------------------------------------------------------------------
*/

it('returns 422 when text exceeds 5000 characters', function () {
    $candidate = Candidate::factory()->create();
    $token = aiAuthCandidate($candidate);

    $longText = str_repeat('a', 5001);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/v1/candidate/ai/improve', [
        'text' => $longText,
    ]);

    $response->assertStatus(422);
});

it('accepts text at exactly 5000 characters', function () {
    Queue::fake();

    $candidate = Candidate::factory()->create();
    $token = aiAuthCandidate($candidate);

    $exactText = str_repeat('a', 5000);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/v1/candidate/ai/improve', [
        'text' => $exactText,
    ]);

    $response->assertStatus(202);
});

/*
|--------------------------------------------------------------------------
| GET /api/v1/candidate/ai/jobs/{id} — Poll Job Status
|--------------------------------------------------------------------------
*/

it('returns pending job status', function () {
    $candidate = Candidate::factory()->create();
    $token = aiAuthCandidate($candidate);

    $aiJob = AIJob::create([
        'candidate_id' => $candidate->id,
        'job_type' => 'summary',
        'input_data' => ['job_title' => 'Engineer', 'years_experience' => 5],
        'status' => 'pending',
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->getJson("/api/v1/candidate/ai/jobs/{$aiJob->id}");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => ['id', 'job_type', 'status', 'created_at'],
        ]);

    expect($response->json('data.status'))->toBe('pending');
    expect($response->json('data.id'))->toBe($aiJob->id);
});

it('returns completed job with result data', function () {
    $candidate = Candidate::factory()->create();
    $token = aiAuthCandidate($candidate);

    $aiJob = AIJob::create([
        'candidate_id' => $candidate->id,
        'job_type' => 'summary',
        'input_data' => ['job_title' => 'Engineer', 'years_experience' => 5],
        'status' => 'completed',
        'result_data' => ['summary' => 'A great professional summary.'],
        'tokens_used' => 150,
        'processing_duration_ms' => 1200,
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->getJson("/api/v1/candidate/ai/jobs/{$aiJob->id}");

    $response->assertStatus(200);
    expect($response->json('data.status'))->toBe('completed');
    expect($response->json('data.result.summary'))->toBe('A great professional summary.');
});

it('returns failed job with error message', function () {
    $candidate = Candidate::factory()->create();
    $token = aiAuthCandidate($candidate);

    $aiJob = AIJob::create([
        'candidate_id' => $candidate->id,
        'job_type' => 'summary',
        'input_data' => ['job_title' => 'Engineer', 'years_experience' => 5],
        'status' => 'failed',
        'error_message' => 'AI service temporarily unavailable. Please try again.',
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->getJson("/api/v1/candidate/ai/jobs/{$aiJob->id}");

    $response->assertStatus(200);
    expect($response->json('data.status'))->toBe('failed');
    expect($response->json('data.error_message'))->toBe('AI service temporarily unavailable. Please try again.');
});

it('returns 404 when polling another candidate job', function () {
    $candidate1 = Candidate::factory()->create();
    $candidate2 = Candidate::factory()->create();
    $token = aiAuthCandidate($candidate1);

    $aiJob = AIJob::create([
        'candidate_id' => $candidate2->id,
        'job_type' => 'summary',
        'input_data' => ['job_title' => 'Engineer', 'years_experience' => 5],
        'status' => 'pending',
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->getJson("/api/v1/candidate/ai/jobs/{$aiJob->id}");

    $response->assertStatus(404);
});

/*
|--------------------------------------------------------------------------
| Rate Limiting — 20/hour
|--------------------------------------------------------------------------
*/

it('returns 429 when hourly rate limit is exceeded', function () {
    Queue::fake();

    $candidate = Candidate::factory()->create();
    $token = aiAuthCandidate($candidate);

    // Create 20 AI jobs directly in the database (within the last hour)
    for ($i = 0; $i < 20; $i++) {
        $job = AIJob::create([
            'candidate_id' => $candidate->id,
            'job_type' => 'summary',
            'input_data' => ['job_title' => 'Engineer', 'years_experience' => 5],
            'status' => 'pending',
        ]);
        $job->forceFill([
            'created_at' => now()->subMinutes(30),
            'updated_at' => now()->subMinutes(30),
        ])->saveQuietly();
    }

    // 21st request should be rate limited
    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/v1/candidate/ai/summary', [
        'job_title' => 'Engineer',
        'years_experience' => 5,
    ]);

    $response->assertStatus(429);
    expect($response->json('error.code'))->toBe('AI_RATE_LIMIT_EXCEEDED');
    expect($response->json('error.details.limit_type'))->toBe('hourly');
    expect($response->json('error.details.limit'))->toBe(20);
    $response->assertHeader('Retry-After');
});

/*
|--------------------------------------------------------------------------
| Rate Limiting — 100/day
|--------------------------------------------------------------------------
*/

it('returns 429 when daily rate limit is exceeded', function () {
    Queue::fake();

    $candidate = Candidate::factory()->create();
    $token = aiAuthCandidate($candidate);

    // Create 100 AI jobs spread across the day but ALL outside the last hour
    // to avoid triggering the hourly limit first.
    // Use query builder to bypass mass-assignment protection for timestamps.
    for ($i = 0; $i < 100; $i++) {
        $job = AIJob::create([
            'candidate_id' => $candidate->id,
            'job_type' => 'summary',
            'input_data' => ['job_title' => 'Engineer', 'years_experience' => 5],
            'status' => 'completed',
        ]);
        // Update timestamps directly to place them outside the hourly window
        $job->forceFill([
            'created_at' => now()->subHours(12)->addMinutes($i),
            'updated_at' => now()->subHours(12)->addMinutes($i),
        ])->saveQuietly();
    }

    // 101st request should be rate limited by daily limit
    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/v1/candidate/ai/summary', [
        'job_title' => 'Engineer',
        'years_experience' => 5,
    ]);

    $response->assertStatus(429);
    expect($response->json('error.code'))->toBe('AI_RATE_LIMIT_EXCEEDED');
    expect($response->json('error.details.limit_type'))->toBe('daily');
    expect($response->json('error.details.limit'))->toBe(100);
    $response->assertHeader('Retry-After');
});

/*
|--------------------------------------------------------------------------
| Rate limiting does not affect other candidates
|--------------------------------------------------------------------------
*/

it('does not rate limit a different candidate', function () {
    Queue::fake();

    $candidate1 = Candidate::factory()->create();
    $candidate2 = Candidate::factory()->create();

    // Fill up candidate1's hourly limit
    for ($i = 0; $i < 20; $i++) {
        AIJob::create([
            'candidate_id' => $candidate1->id,
            'job_type' => 'summary',
            'input_data' => ['job_title' => 'Engineer', 'years_experience' => 5],
            'status' => 'pending',
        ]);
    }

    // candidate2 should still be able to create jobs
    $token = aiAuthCandidate($candidate2);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/v1/candidate/ai/summary', [
        'job_title' => 'Engineer',
        'years_experience' => 5,
    ]);

    $response->assertStatus(202);
});

/*
|--------------------------------------------------------------------------
| ProcessAIJob — Successful processing
|--------------------------------------------------------------------------
*/

it('processes a summary AI job successfully via the mock service', function () {
    $candidate = Candidate::factory()->create();

    $aiJob = AIJob::create([
        'candidate_id' => $candidate->id,
        'job_type' => 'summary',
        'input_data' => ['job_title' => 'Software Engineer', 'years_experience' => 5],
        'status' => 'pending',
    ]);

    // Run the job synchronously with the mock service (0 delay in test env)
    $job = new ProcessAIJob($aiJob);
    $job->handle(app(OpenAIServiceInterface::class));

    $aiJob->refresh();
    expect($aiJob->status)->toBe('completed');
    expect($aiJob->result_data)->not->toBeNull();
    expect($aiJob->result_data)->toHaveKey('summary');
    expect($aiJob->tokens_used)->toBeGreaterThan(0);
    expect($aiJob->processing_duration_ms)->not->toBeNull();
});

it('processes a bullets AI job successfully', function () {
    $candidate = Candidate::factory()->create();

    $aiJob = AIJob::create([
        'candidate_id' => $candidate->id,
        'job_type' => 'bullets',
        'input_data' => [
            'job_title' => 'Senior Developer',
            'company_name' => 'Acme Corp',
            'description' => 'Built web applications.',
        ],
        'status' => 'pending',
    ]);

    $job = new ProcessAIJob($aiJob);
    $job->handle(app(OpenAIServiceInterface::class));

    $aiJob->refresh();
    expect($aiJob->status)->toBe('completed');
    expect($aiJob->result_data)->toHaveKey('bullets');
    expect($aiJob->result_data['bullets'])->toBeArray();
    expect(count($aiJob->result_data['bullets']))->toBeGreaterThan(0);
});

it('processes a skills AI job successfully', function () {
    $candidate = Candidate::factory()->create();

    $aiJob = AIJob::create([
        'candidate_id' => $candidate->id,
        'job_type' => 'skills',
        'input_data' => [
            'job_title' => 'Data Scientist',
            'existing_skills' => ['Python'],
        ],
        'status' => 'pending',
    ]);

    $job = new ProcessAIJob($aiJob);
    $job->handle(app(OpenAIServiceInterface::class));

    $aiJob->refresh();
    expect($aiJob->status)->toBe('completed');
    expect($aiJob->result_data)->toHaveKey('skills');
    expect($aiJob->result_data['skills'])->toHaveKeys(['technical', 'soft']);
});

it('processes an ats_optimize AI job successfully', function () {
    $candidate = Candidate::factory()->create();

    $aiJob = AIJob::create([
        'candidate_id' => $candidate->id,
        'job_type' => 'ats_optimize',
        'input_data' => [
            'job_description' => 'Looking for a React developer.',
            'resume_content' => 'Experienced web developer.',
        ],
        'status' => 'pending',
    ]);

    $job = new ProcessAIJob($aiJob);
    $job->handle(app(OpenAIServiceInterface::class));

    $aiJob->refresh();
    expect($aiJob->status)->toBe('completed');
    expect($aiJob->result_data)->toHaveKeys(['missing_keywords', 'present_keywords', 'suggestions']);
});

it('processes an improve AI job successfully', function () {
    $candidate = Candidate::factory()->create();

    $aiJob = AIJob::create([
        'candidate_id' => $candidate->id,
        'job_type' => 'improve',
        'input_data' => ['text' => 'I did stuff at my job.'],
        'status' => 'pending',
    ]);

    $job = new ProcessAIJob($aiJob);
    $job->handle(app(OpenAIServiceInterface::class));

    $aiJob->refresh();
    expect($aiJob->status)->toBe('completed');
    expect($aiJob->result_data)->toHaveKeys(['original_text', 'improved_text']);
});

/*
|--------------------------------------------------------------------------
| Authentication required
|--------------------------------------------------------------------------
*/

it('returns 401 when accessing AI endpoints without authentication', function () {
    $response = $this->postJson('/api/v1/candidate/ai/summary', [
        'job_title' => 'Engineer',
        'years_experience' => 5,
    ]);
    $response->assertStatus(401);
});

it('returns 401 when polling AI job without authentication', function () {
    $response = $this->getJson('/api/v1/candidate/ai/jobs/some-id');
    $response->assertStatus(401);
});

/*
|--------------------------------------------------------------------------
| All 5 job types create pending jobs
|--------------------------------------------------------------------------
*/

it('creates pending jobs for all 5 AI job types', function () {
    Queue::fake();

    $candidate = Candidate::factory()->create();
    $token = aiAuthCandidate($candidate);

    $requests = [
        ['url' => '/api/v1/candidate/ai/summary', 'data' => ['job_title' => 'Engineer', 'years_experience' => 5]],
        ['url' => '/api/v1/candidate/ai/bullets', 'data' => ['job_title' => 'Engineer', 'company_name' => 'Acme', 'description' => 'Built things']],
        ['url' => '/api/v1/candidate/ai/skills', 'data' => ['job_title' => 'Engineer']],
        ['url' => '/api/v1/candidate/ai/ats-optimize', 'data' => ['job_description' => 'Need React dev', 'resume_content' => ['summary' => 'I know React', 'skills' => ['React']]]],
        ['url' => '/api/v1/candidate/ai/improve', 'data' => ['text' => 'I did stuff']],
    ];

    $expectedTypes = ['summary', 'bullets', 'skills', 'ats_optimize', 'improve'];

    foreach ($requests as $i => $req) {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson($req['url'], $req['data']);

        $response->assertStatus(202);
        expect($response->json('data.status'))->toBe('pending');

        $aiJob = AIJob::find($response->json('data.job_id'));
        expect($aiJob->job_type)->toBe($expectedTypes[$i]);
    }

    expect(AIJob::where('candidate_id', $candidate->id)->count())->toBe(5);
});
