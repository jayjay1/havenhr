<?php

use App\Models\Candidate;
use App\Models\Company;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\PipelineStage;
use App\Models\Role;
use App\Models\Resume;
use App\Models\User;
use App\Services\JobPostingService;
use App\Services\PipelineService;
use App\Services\RoleTemplateService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->company = Company::factory()->create(['settings' => ['logo_url' => 'https://example.com/logo.png']]);
    $roleTemplateService = app(RoleTemplateService::class);
    $this->roles = $roleTemplateService->createDefaultRoles($this->company);

    $tenantContext = app(TenantContext::class);
    $tenantContext->setTenantId($this->company->id);

    $this->authUser = User::withoutGlobalScopes()->create([
        'name' => 'Auth Owner',
        'email' => 'authowner@test.com',
        'password_hash' => Hash::make('SecurePass123!', ['rounds' => 12]),
        'tenant_id' => $this->company->id,
        'is_active' => true,
    ]);
    $ownerRole = $this->roles->get('Owner');
    $this->authUser->roles()->attach($ownerRole->id, ['assigned_at' => now()]);

    $this->authToken = JWTAuth::claims([
        'tenant_id' => $this->company->id,
        'role' => 'Owner',
    ])->fromUser($this->authUser);
});

function validJobPayload(array $overrides = []): array
{
    return array_merge([
        'title' => 'Senior Laravel Developer',
        'description' => 'We are looking for a senior Laravel developer.',
        'location' => 'San Francisco, CA',
        'employment_type' => 'full-time',
        'department' => 'Engineering',
        'salary_min' => 120000,
        'salary_max' => 180000,
        'salary_currency' => 'USD',
        'remote_status' => 'hybrid',
    ], $overrides);
}

// --- Job CRUD ---

it('creates a job posting with draft status and default pipeline stages', function () {
    Event::fake([App\Events\JobPostingCreated::class, App\Events\JobPostingUpdated::class, App\Events\JobPostingStatusChanged::class, App\Events\JobPostingDeleted::class, App\Events\ApplicationStageChanged::class, App\Events\CandidateApplied::class]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->postJson('/api/v1/jobs', validJobPayload());

    $response->assertStatus(201);
    expect($response->json('data.status'))->toBe('draft');
    expect($response->json('data.title'))->toBe('Senior Laravel Developer');
    expect($response->json('data.slug'))->toContain('senior-laravel-developer');

    $jobId = $response->json('data.id');
    $stages = PipelineStage::where('job_posting_id', $jobId)->orderBy('sort_order')->get();
    expect($stages)->toHaveCount(6);
    expect($stages->pluck('name')->toArray())->toBe(['Applied', 'Screening', 'Interview', 'Offer', 'Hired', 'Rejected']);
});

it('returns 422 for missing required fields on create', function () {
    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->postJson('/api/v1/jobs', []);

    $response->assertStatus(422);
});

it('returns 422 when salary_min exceeds salary_max', function () {
    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->postJson('/api/v1/jobs', validJobPayload([
        'salary_min' => 200000,
        'salary_max' => 100000,
    ]));

    $response->assertStatus(422);
});

it('updates a job posting', function () {
    Event::fake([App\Events\JobPostingCreated::class, App\Events\JobPostingUpdated::class, App\Events\JobPostingStatusChanged::class, App\Events\JobPostingDeleted::class, App\Events\ApplicationStageChanged::class, App\Events\CandidateApplied::class]);

    $job = JobPosting::factory()->create([
        'tenant_id' => $this->company->id,
        'created_by' => $this->authUser->id,
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->putJson("/api/v1/jobs/{$job->id}", [
        'title' => 'Updated Title',
    ]);

    $response->assertStatus(200);
    expect($response->json('data.title'))->toBe('Updated Title');
});

it('rejects update on archived job posting', function () {
    $job = JobPosting::factory()->archived()->create([
        'tenant_id' => $this->company->id,
        'created_by' => $this->authUser->id,
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->putJson("/api/v1/jobs/{$job->id}", [
        'title' => 'Updated Title',
    ]);

    $response->assertStatus(422);
});

it('soft-deletes a draft job posting', function () {
    Event::fake([App\Events\JobPostingCreated::class, App\Events\JobPostingUpdated::class, App\Events\JobPostingStatusChanged::class, App\Events\JobPostingDeleted::class, App\Events\ApplicationStageChanged::class, App\Events\CandidateApplied::class]);

    $job = JobPosting::factory()->create([
        'tenant_id' => $this->company->id,
        'created_by' => $this->authUser->id,
        'status' => 'draft',
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->deleteJson("/api/v1/jobs/{$job->id}");

    $response->assertStatus(204);
    expect(JobPosting::find($job->id))->toBeNull();
    expect(JobPosting::withTrashed()->find($job->id))->not->toBeNull();
});

it('rejects delete on non-draft job posting', function () {
    $job = JobPosting::factory()->published()->create([
        'tenant_id' => $this->company->id,
        'created_by' => $this->authUser->id,
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->deleteJson("/api/v1/jobs/{$job->id}");

    $response->assertStatus(422);
});

it('shows job detail with pipeline stages and application counts', function () {
    Event::fake([App\Events\JobPostingCreated::class, App\Events\JobPostingUpdated::class, App\Events\JobPostingStatusChanged::class, App\Events\JobPostingDeleted::class, App\Events\ApplicationStageChanged::class, App\Events\CandidateApplied::class]);

    $createResponse = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->postJson('/api/v1/jobs', validJobPayload());

    $jobId = $createResponse->json('data.id');

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->getJson("/api/v1/jobs/{$jobId}");

    $response->assertStatus(200);
    expect($response->json('data.pipeline_stages'))->toHaveCount(6);
    expect($response->json('data.application_count'))->toBe(0);
});

it('lists tenant job postings with pagination', function () {
    Event::fake([App\Events\JobPostingCreated::class, App\Events\JobPostingUpdated::class, App\Events\JobPostingStatusChanged::class, App\Events\JobPostingDeleted::class, App\Events\ApplicationStageChanged::class, App\Events\CandidateApplied::class]);

    for ($i = 0; $i < 3; $i++) {
        JobPosting::factory()->create([
            'tenant_id' => $this->company->id,
            'created_by' => $this->authUser->id,
        ]);
    }

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->getJson('/api/v1/jobs?per_page=2');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('meta.total'))->toBe(3);
});

it('filters tenant job postings by status', function () {
    Event::fake([App\Events\JobPostingCreated::class, App\Events\JobPostingUpdated::class, App\Events\JobPostingStatusChanged::class, App\Events\JobPostingDeleted::class, App\Events\ApplicationStageChanged::class, App\Events\CandidateApplied::class]);

    JobPosting::factory()->create([
        'tenant_id' => $this->company->id,
        'created_by' => $this->authUser->id,
        'status' => 'draft',
    ]);
    JobPosting::factory()->published()->create([
        'tenant_id' => $this->company->id,
        'created_by' => $this->authUser->id,
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->getJson('/api/v1/jobs?status=draft');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.status'))->toBe('draft');
});

// --- Status Transitions ---

it('transitions draft to published and sets published_at', function () {
    Event::fake([App\Events\JobPostingCreated::class, App\Events\JobPostingUpdated::class, App\Events\JobPostingStatusChanged::class, App\Events\JobPostingDeleted::class, App\Events\ApplicationStageChanged::class, App\Events\CandidateApplied::class]);

    $job = JobPosting::factory()->create([
        'tenant_id' => $this->company->id,
        'created_by' => $this->authUser->id,
        'status' => 'draft',
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->patchJson("/api/v1/jobs/{$job->id}/status", ['status' => 'published']);

    $response->assertStatus(200);
    expect($response->json('data.status'))->toBe('published');
    expect($response->json('data.published_at'))->not->toBeNull();
});

it('preserves published_at on re-publish', function () {
    Event::fake([App\Events\JobPostingCreated::class, App\Events\JobPostingUpdated::class, App\Events\JobPostingStatusChanged::class, App\Events\JobPostingDeleted::class, App\Events\ApplicationStageChanged::class, App\Events\CandidateApplied::class]);

    $originalPublishedAt = now()->subDays(10);
    $job = JobPosting::factory()->create([
        'tenant_id' => $this->company->id,
        'created_by' => $this->authUser->id,
        'status' => 'closed',
        'published_at' => $originalPublishedAt,
        'closed_at' => now(),
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->patchJson("/api/v1/jobs/{$job->id}/status", ['status' => 'published']);

    $response->assertStatus(200);
    $job->refresh();
    expect($job->published_at->format('Y-m-d'))->toBe($originalPublishedAt->format('Y-m-d'));
});

it('rejects invalid status transition', function () {
    $job = JobPosting::factory()->create([
        'tenant_id' => $this->company->id,
        'created_by' => $this->authUser->id,
        'status' => 'draft',
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->patchJson("/api/v1/jobs/{$job->id}/status", ['status' => 'closed']);

    $response->assertStatus(422);
    expect($response->json('error.code'))->toBe('INVALID_STATUS_TRANSITION');
});

// --- Slug Generation ---

it('generates URL-safe slugs', function () {
    $service = app(JobPostingService::class);

    $slug = $service->generateSlug('Senior Laravel Developer!');
    expect($slug)->toMatch('/^senior-laravel-developer-[a-f0-9]{8}$/');

    $slug2 = $service->generateSlug('  Multiple   Spaces  ');
    expect($slug2)->toMatch('/^multiple-spaces-[a-f0-9]{8}$/');

    $slug3 = $service->generateSlug('Special @#$ Characters');
    expect($slug3)->toMatch('/^special-characters-[a-f0-9]{8}$/');
});

it('generates unique slugs for same title', function () {
    $service = app(JobPostingService::class);
    $slug1 = $service->generateSlug('Same Title');
    $slug2 = $service->generateSlug('Same Title');
    expect($slug1)->not->toBe($slug2);
});

// --- Pipeline Stages ---

it('lists pipeline stages for a job posting', function () {
    Event::fake([App\Events\JobPostingCreated::class, App\Events\JobPostingUpdated::class, App\Events\JobPostingStatusChanged::class, App\Events\JobPostingDeleted::class, App\Events\ApplicationStageChanged::class, App\Events\CandidateApplied::class]);

    $createResponse = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->postJson('/api/v1/jobs', validJobPayload());

    $jobId = $createResponse->json('data.id');

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->getJson("/api/v1/jobs/{$jobId}/stages");

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(6);
});

it('adds a pipeline stage', function () {
    Event::fake([App\Events\JobPostingCreated::class, App\Events\JobPostingUpdated::class, App\Events\JobPostingStatusChanged::class, App\Events\JobPostingDeleted::class, App\Events\ApplicationStageChanged::class, App\Events\CandidateApplied::class]);

    $createResponse = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->postJson('/api/v1/jobs', validJobPayload());

    $jobId = $createResponse->json('data.id');

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->postJson("/api/v1/jobs/{$jobId}/stages", [
        'name' => 'Technical Assessment',
        'sort_order' => 6,
    ]);

    $response->assertStatus(201);
    expect($response->json('data.name'))->toBe('Technical Assessment');
});

it('reorders pipeline stages', function () {
    Event::fake([App\Events\JobPostingCreated::class, App\Events\JobPostingUpdated::class, App\Events\JobPostingStatusChanged::class, App\Events\JobPostingDeleted::class, App\Events\ApplicationStageChanged::class, App\Events\CandidateApplied::class]);

    $createResponse = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->postJson('/api/v1/jobs', validJobPayload());

    $jobId = $createResponse->json('data.id');
    $stages = PipelineStage::where('job_posting_id', $jobId)->orderBy('sort_order')->get();

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->putJson("/api/v1/jobs/{$jobId}/stages/reorder", [
        'stages' => [
            ['id' => $stages[0]->id, 'sort_order' => 1],
            ['id' => $stages[1]->id, 'sort_order' => 0],
        ],
    ]);

    $response->assertStatus(200);
});

it('removes a pipeline stage without applications', function () {
    Event::fake([App\Events\JobPostingCreated::class, App\Events\JobPostingUpdated::class, App\Events\JobPostingStatusChanged::class, App\Events\JobPostingDeleted::class, App\Events\ApplicationStageChanged::class, App\Events\CandidateApplied::class]);

    $createResponse = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->postJson('/api/v1/jobs', validJobPayload());

    $jobId = $createResponse->json('data.id');
    $stage = PipelineStage::where('job_posting_id', $jobId)->orderBy('sort_order', 'desc')->first();

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->deleteJson("/api/v1/jobs/{$jobId}/stages/{$stage->id}");

    $response->assertStatus(204);
});

it('rejects removing a pipeline stage with applications', function () {
    Event::fake([App\Events\JobPostingCreated::class, App\Events\JobPostingUpdated::class, App\Events\JobPostingStatusChanged::class, App\Events\JobPostingDeleted::class, App\Events\ApplicationStageChanged::class, App\Events\CandidateApplied::class]);

    $createResponse = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->postJson('/api/v1/jobs', validJobPayload());

    $jobId = $createResponse->json('data.id');
    $stage = PipelineStage::where('job_posting_id', $jobId)->orderBy('sort_order')->first();

    $candidate = Candidate::factory()->create();
    $resume = Resume::create([
        'candidate_id' => $candidate->id,
        'title' => 'Test Resume',
        'template_slug' => 'clean',
        'content' => ['personal_info' => ['name' => 'Test']],
        'is_complete' => true,
    ]);

    JobApplication::create([
        'candidate_id' => $candidate->id,
        'job_posting_id' => $jobId,
        'resume_id' => $resume->id,
        'resume_snapshot' => $resume->content,
        'pipeline_stage_id' => $stage->id,
        'status' => 'submitted',
        'applied_at' => now(),
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->deleteJson("/api/v1/jobs/{$jobId}/stages/{$stage->id}");

    $response->assertStatus(422);
    expect($response->json('error.code'))->toBe('STAGE_HAS_APPLICATIONS');
});

// --- Application Integration ---

it('moves an application between stages', function () {
    Event::fake([App\Events\JobPostingCreated::class, App\Events\JobPostingUpdated::class, App\Events\JobPostingStatusChanged::class, App\Events\JobPostingDeleted::class, App\Events\ApplicationStageChanged::class, App\Events\CandidateApplied::class]);

    $createResponse = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->postJson('/api/v1/jobs', validJobPayload());

    $jobId = $createResponse->json('data.id');
    $stages = PipelineStage::where('job_posting_id', $jobId)->orderBy('sort_order')->get();

    $candidate = Candidate::factory()->create();
    $resume = Resume::create([
        'candidate_id' => $candidate->id,
        'title' => 'Test Resume',
        'template_slug' => 'clean',
        'content' => ['personal_info' => ['name' => 'Test']],
        'is_complete' => true,
    ]);

    $application = JobApplication::create([
        'candidate_id' => $candidate->id,
        'job_posting_id' => $jobId,
        'resume_id' => $resume->id,
        'resume_snapshot' => $resume->content,
        'pipeline_stage_id' => $stages[0]->id,
        'status' => 'submitted',
        'applied_at' => now(),
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->postJson("/api/v1/applications/{$application->id}/move", [
        'stage_id' => $stages[1]->id,
    ]);

    $response->assertStatus(200);
    $application->refresh();
    expect($application->pipeline_stage_id)->toBe($stages[1]->id);
});

it('rejects moving application to stage from different job', function () {
    Event::fake([App\Events\JobPostingCreated::class, App\Events\JobPostingUpdated::class, App\Events\JobPostingStatusChanged::class, App\Events\JobPostingDeleted::class, App\Events\ApplicationStageChanged::class, App\Events\CandidateApplied::class]);

    $createResponse1 = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->postJson('/api/v1/jobs', validJobPayload());
    $jobId1 = $createResponse1->json('data.id');

    $createResponse2 = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->postJson('/api/v1/jobs', validJobPayload(['title' => 'Another Job']));
    $jobId2 = $createResponse2->json('data.id');

    $stages1 = PipelineStage::where('job_posting_id', $jobId1)->orderBy('sort_order')->get();
    $stages2 = PipelineStage::where('job_posting_id', $jobId2)->orderBy('sort_order')->get();

    $candidate = Candidate::factory()->create();
    $resume = Resume::create([
        'candidate_id' => $candidate->id,
        'title' => 'Test Resume',
        'template_slug' => 'clean',
        'content' => ['personal_info' => ['name' => 'Test']],
        'is_complete' => true,
    ]);

    $application = JobApplication::create([
        'candidate_id' => $candidate->id,
        'job_posting_id' => $jobId1,
        'resume_id' => $resume->id,
        'resume_snapshot' => $resume->content,
        'pipeline_stage_id' => $stages1[0]->id,
        'status' => 'submitted',
        'applied_at' => now(),
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->postJson("/api/v1/applications/{$application->id}/move", [
        'stage_id' => $stages2[0]->id,
    ]);

    $response->assertStatus(422);
    expect($response->json('error.code'))->toBe('INVALID_STAGE');
});

it('gets transition history for an application', function () {
    Event::fake([App\Events\JobPostingCreated::class, App\Events\JobPostingUpdated::class, App\Events\JobPostingStatusChanged::class, App\Events\JobPostingDeleted::class, App\Events\ApplicationStageChanged::class, App\Events\CandidateApplied::class]);

    $createResponse = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->postJson('/api/v1/jobs', validJobPayload());

    $jobId = $createResponse->json('data.id');
    $stages = PipelineStage::where('job_posting_id', $jobId)->orderBy('sort_order')->get();

    $candidate = Candidate::factory()->create();
    $resume = Resume::create([
        'candidate_id' => $candidate->id,
        'title' => 'Test Resume',
        'template_slug' => 'clean',
        'content' => ['personal_info' => ['name' => 'Test']],
        'is_complete' => true,
    ]);

    $application = JobApplication::create([
        'candidate_id' => $candidate->id,
        'job_posting_id' => $jobId,
        'resume_id' => $resume->id,
        'resume_snapshot' => $resume->content,
        'pipeline_stage_id' => $stages[0]->id,
        'status' => 'submitted',
        'applied_at' => now(),
    ]);

    $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->postJson("/api/v1/applications/{$application->id}/move", [
        'stage_id' => $stages[1]->id,
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->getJson("/api/v1/applications/{$application->id}/transitions");

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.to_stage.name'))->toBe('Screening');
});

// --- Public Job Board ---

it('lists only published jobs on public board', function () {
    JobPosting::factory()->create([
        'tenant_id' => $this->company->id,
        'created_by' => $this->authUser->id,
        'status' => 'draft',
    ]);
    JobPosting::factory()->published()->create([
        'tenant_id' => $this->company->id,
        'created_by' => $this->authUser->id,
    ]);

    $response = $this->getJson('/api/v1/public/jobs');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.company_name'))->toBe($this->company->name);
    expect($response->json('data.0'))->not->toHaveKey('tenant_id');
    expect($response->json('data.0'))->not->toHaveKey('created_by');
});

it('shows public job detail by slug with OG metadata', function () {
    $job = JobPosting::factory()->published()->create([
        'tenant_id' => $this->company->id,
        'created_by' => $this->authUser->id,
    ]);

    $response = $this->getJson("/api/v1/public/jobs/{$job->slug}");

    $response->assertStatus(200);
    expect($response->json('data.title'))->toBe($job->title);
    expect($response->json('data.company_name'))->toBe($this->company->name);
    expect($response->json('data.company_logo_url'))->toBe('https://example.com/logo.png');
    expect($response->json('data.og.type'))->toBe('website');
    expect($response->json('data.og.title'))->toContain($job->title);
});

it('returns 404 for non-published job on public detail', function () {
    $job = JobPosting::factory()->create([
        'tenant_id' => $this->company->id,
        'created_by' => $this->authUser->id,
        'status' => 'draft',
    ]);

    $response = $this->getJson("/api/v1/public/jobs/{$job->slug}");

    $response->assertStatus(404);
});

it('searches public jobs by title', function () {
    JobPosting::factory()->published()->create([
        'tenant_id' => $this->company->id,
        'created_by' => $this->authUser->id,
        'title' => 'Laravel Developer',
    ]);
    JobPosting::factory()->published()->create([
        'tenant_id' => $this->company->id,
        'created_by' => $this->authUser->id,
        'title' => 'React Developer',
    ]);

    $response = $this->getJson('/api/v1/public/jobs?q=Laravel');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.title'))->toBe('Laravel Developer');
});

it('filters public jobs by employment type', function () {
    JobPosting::factory()->published()->create([
        'tenant_id' => $this->company->id,
        'created_by' => $this->authUser->id,
        'employment_type' => 'full-time',
    ]);
    JobPosting::factory()->published()->create([
        'tenant_id' => $this->company->id,
        'created_by' => $this->authUser->id,
        'employment_type' => 'contract',
    ]);

    $response = $this->getJson('/api/v1/public/jobs?employment_type=full-time');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.employment_type'))->toBe('full-time');
});

// --- Tenant Isolation ---

it('isolates job postings per tenant', function () {
    $otherCompany = Company::factory()->create();
    JobPosting::factory()->create([
        'tenant_id' => $otherCompany->id,
        'created_by' => $this->authUser->id,
    ]);
    JobPosting::factory()->create([
        'tenant_id' => $this->company->id,
        'created_by' => $this->authUser->id,
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->getJson('/api/v1/jobs');

    $response->assertStatus(200);
    expect($response->json('meta.total'))->toBe(1);
});

// --- Employer Application Endpoints ---

it('lists applications for a job posting', function () {
    Event::fake([App\Events\JobPostingCreated::class, App\Events\JobPostingUpdated::class, App\Events\JobPostingStatusChanged::class, App\Events\JobPostingDeleted::class, App\Events\ApplicationStageChanged::class, App\Events\CandidateApplied::class]);

    $createResponse = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->postJson('/api/v1/jobs', validJobPayload());

    $jobId = $createResponse->json('data.id');

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->getJson("/api/v1/jobs/{$jobId}/applications");

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(0);
});

it('shows application detail with transitions', function () {
    Event::fake([App\Events\JobPostingCreated::class, App\Events\JobPostingUpdated::class, App\Events\JobPostingStatusChanged::class, App\Events\JobPostingDeleted::class, App\Events\ApplicationStageChanged::class, App\Events\CandidateApplied::class]);

    $createResponse = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->postJson('/api/v1/jobs', validJobPayload());

    $jobId = $createResponse->json('data.id');
    $stage = PipelineStage::where('job_posting_id', $jobId)->orderBy('sort_order')->first();

    $candidate = Candidate::factory()->create();
    $resume = Resume::create([
        'candidate_id' => $candidate->id,
        'title' => 'Test Resume',
        'template_slug' => 'clean',
        'content' => ['personal_info' => ['name' => 'Test']],
        'is_complete' => true,
    ]);

    $application = JobApplication::create([
        'candidate_id' => $candidate->id,
        'job_posting_id' => $jobId,
        'resume_id' => $resume->id,
        'resume_snapshot' => $resume->content,
        'pipeline_stage_id' => $stage->id,
        'status' => 'submitted',
        'applied_at' => now(),
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->getJson("/api/v1/applications/{$application->id}");

    $response->assertStatus(200);
    expect($response->json('data.current_stage'))->toBe('Applied');
    expect($response->json('data.transitions'))->toBeArray();
});

// --- Public endpoints require no auth ---

it('public job board requires no authentication', function () {
    $response = $this->getJson('/api/v1/public/jobs');
    $response->assertStatus(200);
});

