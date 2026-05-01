<?php

use App\Models\Candidate;
use App\Models\Company;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\PipelineStage;
use App\Models\Resume;
use App\Models\StageTransition;
use App\Models\User;
use App\Services\PipelineService;
use App\Services\RoleTemplateService;
use App\Services\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);

    $this->company = Company::factory()->create();
    $roleTemplateService = app(RoleTemplateService::class);
    $this->roles = $roleTemplateService->createDefaultRoles($this->company);

    $tenantContext = app(TenantContext::class);
    $tenantContext->setTenantId($this->company->id);

    $this->authUser = User::withoutGlobalScopes()->create([
        'name' => 'Pipeline Test User',
        'email' => 'pipeline-test@test.com',
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

    Event::fake([
        App\Events\JobPostingCreated::class,
        App\Events\JobPostingUpdated::class,
        App\Events\JobPostingStatusChanged::class,
        App\Events\JobPostingDeleted::class,
        App\Events\ApplicationStageChanged::class,
        App\Events\CandidateApplied::class,
    ]);
});

/**
 * Helper: create a job posting with default pipeline stages via the API.
 */
function createJobWithStages($context): array
{
    $response = $context->withHeaders([
        'Authorization' => "Bearer {$context->authToken}",
    ])->postJson('/api/v1/jobs', [
        'title' => 'Test Job',
        'description' => 'A test job posting.',
        'location' => 'Remote',
        'employment_type' => 'full-time',
    ]);

    $jobId = $response->json('data.id');
    $stages = PipelineStage::where('job_posting_id', $jobId)->orderBy('sort_order')->get();

    return ['jobId' => $jobId, 'stages' => $stages];
}

/**
 * Helper: create a candidate with a resume and application in a given stage.
 */
function createApplication(string $jobId, string $stageId, ?string $name = null, ?string $email = null): JobApplication
{
    $candidate = Candidate::factory()->create(array_filter([
        'name' => $name,
        'email' => $email,
    ]));

    $resume = Resume::create([
        'candidate_id' => $candidate->id,
        'title' => 'Test Resume',
        'template_slug' => 'clean',
        'content' => ['personal_info' => ['name' => $candidate->name]],
        'is_complete' => true,
    ]);

    return JobApplication::create([
        'candidate_id' => $candidate->id,
        'job_posting_id' => $jobId,
        'resume_id' => $resume->id,
        'resume_snapshot' => $resume->content,
        'pipeline_stage_id' => $stageId,
        'status' => 'submitted',
        'applied_at' => now(),
    ]);
}

// --- Bulk Move ---

it('bulk moves applications to a target stage', function () {
    $job = createJobWithStages($this);
    $appliedStage = $job['stages'][0]; // Applied
    $interviewStage = $job['stages'][2]; // Interview

    $app1 = createApplication($job['jobId'], $appliedStage->id);
    $app2 = createApplication($job['jobId'], $appliedStage->id);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->postJson('/api/v1/applications/bulk-move', [
        'application_ids' => [$app1->id, $app2->id],
        'stage_id' => $interviewStage->id,
    ]);

    $response->assertStatus(200);
    expect($response->json('data.success_count'))->toBe(2);
    expect($response->json('data.failed_count'))->toBe(0);
    expect($response->json('data.failed_ids'))->toBe([]);

    $app1->refresh();
    $app2->refresh();
    expect($app1->pipeline_stage_id)->toBe($interviewStage->id);
    expect($app2->pipeline_stage_id)->toBe($interviewStage->id);

    // Verify stage transitions were created
    expect(StageTransition::where('job_application_id', $app1->id)->count())->toBe(1);
    expect(StageTransition::where('job_application_id', $app2->id)->count())->toBe(1);
});

it('bulk move fails for applications from different job posting', function () {
    $job1 = createJobWithStages($this);
    $job2 = createJobWithStages($this);

    $app1 = createApplication($job1['jobId'], $job1['stages'][0]->id);
    $app2 = createApplication($job2['jobId'], $job2['stages'][0]->id);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->postJson('/api/v1/applications/bulk-move', [
        'application_ids' => [$app1->id, $app2->id],
        'stage_id' => $job1['stages'][2]->id,
    ]);

    $response->assertStatus(200);
    expect($response->json('data.success_count'))->toBe(1);
    expect($response->json('data.failed_count'))->toBe(1);
    expect($response->json('data.failed_ids'))->toContain($app2->id);
});

it('returns 422 for bulk move with empty application_ids', function () {
    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->postJson('/api/v1/applications/bulk-move', [
        'application_ids' => [],
        'stage_id' => 'some-uuid',
    ]);

    $response->assertStatus(422);
});

// --- Bulk Reject ---

it('bulk rejects applications to their Rejected stage', function () {
    $job = createJobWithStages($this);
    $appliedStage = $job['stages'][0]; // Applied
    $rejectedStage = $job['stages']->firstWhere('name', 'Rejected');

    $app1 = createApplication($job['jobId'], $appliedStage->id);
    $app2 = createApplication($job['jobId'], $appliedStage->id);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->postJson('/api/v1/applications/bulk-reject', [
        'application_ids' => [$app1->id, $app2->id],
    ]);

    $response->assertStatus(200);
    expect($response->json('data.success_count'))->toBe(2);
    expect($response->json('data.failed_count'))->toBe(0);

    $app1->refresh();
    $app2->refresh();
    expect($app1->pipeline_stage_id)->toBe($rejectedStage->id);
    expect($app2->pipeline_stage_id)->toBe($rejectedStage->id);

    // Verify stage transitions were created
    expect(StageTransition::where('job_application_id', $app1->id)->count())->toBe(1);
    expect(StageTransition::where('job_application_id', $app2->id)->count())->toBe(1);
});

it('returns 422 for bulk reject with empty application_ids', function () {
    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->postJson('/api/v1/applications/bulk-reject', [
        'application_ids' => [],
    ]);

    $response->assertStatus(422);
});

// --- Stage Update (name + color) ---

it('updates a pipeline stage name', function () {
    $job = createJobWithStages($this);
    $stage = $job['stages'][1]; // Screening

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->patchJson("/api/v1/jobs/{$job['jobId']}/stages/{$stage->id}", [
        'name' => 'Phone Screen',
    ]);

    $response->assertStatus(200);
    expect($response->json('data.name'))->toBe('Phone Screen');
    expect($response->json('data.id'))->toBe($stage->id);

    $stage->refresh();
    expect($stage->name)->toBe('Phone Screen');
});

it('updates a pipeline stage color', function () {
    $job = createJobWithStages($this);
    $stage = $job['stages'][1];

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->patchJson("/api/v1/jobs/{$job['jobId']}/stages/{$stage->id}", [
        'color' => '#3B82F6',
    ]);

    $response->assertStatus(200);
    expect($response->json('data.color'))->toBe('#3B82F6');

    $stage->refresh();
    expect($stage->color)->toBe('#3B82F6');
});

it('updates both name and color simultaneously', function () {
    $job = createJobWithStages($this);
    $stage = $job['stages'][2]; // Interview

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->patchJson("/api/v1/jobs/{$job['jobId']}/stages/{$stage->id}", [
        'name' => 'Technical Interview',
        'color' => '#10B981',
    ]);

    $response->assertStatus(200);
    expect($response->json('data.name'))->toBe('Technical Interview');
    expect($response->json('data.color'))->toBe('#10B981');
});

it('clears stage color by setting null', function () {
    $job = createJobWithStages($this);
    $stage = $job['stages'][1];

    // First set a color
    $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->patchJson("/api/v1/jobs/{$job['jobId']}/stages/{$stage->id}", [
        'color' => '#3B82F6',
    ]);

    // Then clear it
    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->patchJson("/api/v1/jobs/{$job['jobId']}/stages/{$stage->id}", [
        'color' => null,
    ]);

    $response->assertStatus(200);
    expect($response->json('data.color'))->toBeNull();
});

// --- Hex Color Validation ---

it('accepts valid hex color codes', function () {
    $job = createJobWithStages($this);
    $stage = $job['stages'][0];

    $validColors = ['#000000', '#FFFFFF', '#3B82F6', '#abcdef', '#AbCdEf'];

    foreach ($validColors as $color) {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->authToken}",
        ])->patchJson("/api/v1/jobs/{$job['jobId']}/stages/{$stage->id}", [
            'color' => $color,
        ]);

        $response->assertStatus(200);
    }
});

it('rejects invalid hex color codes', function () {
    $job = createJobWithStages($this);
    $stage = $job['stages'][0];

    $invalidColors = ['red', '#GGG', '3B82F6', '#3B82F6FF', '#12345', 'not-a-color', '#xyz'];

    foreach ($invalidColors as $color) {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->authToken}",
        ])->patchJson("/api/v1/jobs/{$job['jobId']}/stages/{$stage->id}", [
            'color' => $color,
        ]);

        $response->assertStatus(422, "Expected 422 for color: {$color}");
    }
});

// --- listStages includes color ---

it('listStages includes color in response', function () {
    $job = createJobWithStages($this);
    $stage = $job['stages'][0];

    // Set a color first
    $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->patchJson("/api/v1/jobs/{$job['jobId']}/stages/{$stage->id}", [
        'color' => '#FF5733',
    ]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->getJson("/api/v1/jobs/{$job['jobId']}/stages");

    $response->assertStatus(200);

    $stageData = collect($response->json('data'))->firstWhere('id', $stage->id);
    expect($stageData)->toHaveKey('color');
    expect($stageData['color'])->toBe('#FF5733');
});

// --- Server-side Search ---

it('filters applications by candidate name with q parameter', function () {
    $job = createJobWithStages($this);
    $appliedStage = $job['stages'][0];

    createApplication($job['jobId'], $appliedStage->id, 'Alice Johnson', 'alice@example.com');
    createApplication($job['jobId'], $appliedStage->id, 'Bob Smith', 'bob@example.com');
    createApplication($job['jobId'], $appliedStage->id, 'Charlie Brown', 'charlie@example.com');

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->getJson("/api/v1/jobs/{$job['jobId']}/applications?q=Alice");

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.candidate_name'))->toBe('Alice Johnson');
});

it('filters applications by candidate email with q parameter', function () {
    $job = createJobWithStages($this);
    $appliedStage = $job['stages'][0];

    createApplication($job['jobId'], $appliedStage->id, 'Alice Johnson', 'alice@example.com');
    createApplication($job['jobId'], $appliedStage->id, 'Bob Smith', 'bob@example.com');

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->getJson("/api/v1/jobs/{$job['jobId']}/applications?q=bob@");

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.candidate_name'))->toBe('Bob Smith');
});

it('search is case-insensitive', function () {
    $job = createJobWithStages($this);
    $appliedStage = $job['stages'][0];

    createApplication($job['jobId'], $appliedStage->id, 'Alice Johnson', 'alice@example.com');

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->getJson("/api/v1/jobs/{$job['jobId']}/applications?q=alice");

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
});

it('returns empty results when search matches nothing', function () {
    $job = createJobWithStages($this);
    $appliedStage = $job['stages'][0];

    createApplication($job['jobId'], $appliedStage->id, 'Alice Johnson', 'alice@example.com');

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->getJson("/api/v1/jobs/{$job['jobId']}/applications?q=nonexistent");

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(0);
});

// --- Server-side Sort ---

it('sorts applications by applied_at descending by default', function () {
    $job = createJobWithStages($this);
    $appliedStage = $job['stages'][0];

    $app1 = createApplication($job['jobId'], $appliedStage->id, 'First Applicant', 'first@example.com');
    $app1->update(['applied_at' => now()->subDays(2)]);

    $app2 = createApplication($job['jobId'], $appliedStage->id, 'Second Applicant', 'second@example.com');
    $app2->update(['applied_at' => now()->subDay()]);

    $app3 = createApplication($job['jobId'], $appliedStage->id, 'Third Applicant', 'third@example.com');
    $app3->update(['applied_at' => now()]);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->getJson("/api/v1/jobs/{$job['jobId']}/applications");

    $response->assertStatus(200);
    $names = collect($response->json('data'))->pluck('candidate_name')->toArray();
    expect($names[0])->toBe('Third Applicant');
    expect($names[2])->toBe('First Applicant');
});

it('sorts applications by candidate_name ascending', function () {
    $job = createJobWithStages($this);
    $appliedStage = $job['stages'][0];

    createApplication($job['jobId'], $appliedStage->id, 'Charlie Brown', 'charlie@example.com');
    createApplication($job['jobId'], $appliedStage->id, 'Alice Johnson', 'alice@example.com');
    createApplication($job['jobId'], $appliedStage->id, 'Bob Smith', 'bob@example.com');

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->getJson("/api/v1/jobs/{$job['jobId']}/applications?sort=candidate_name");

    $response->assertStatus(200);
    $names = collect($response->json('data'))->pluck('candidate_name')->toArray();
    expect($names)->toBe(['Alice Johnson', 'Bob Smith', 'Charlie Brown']);
});

// --- notification_eligible in event payload ---

it('includes notification_eligible in moveApplication event', function () {
    Event::fake([App\Events\ApplicationStageChanged::class]);

    $job = createJobWithStages($this);
    $appliedStage = $job['stages'][0];
    $screeningStage = $job['stages'][1];

    $app = createApplication($job['jobId'], $appliedStage->id);

    $this->withHeaders([
        'Authorization' => "Bearer {$this->authToken}",
    ])->postJson("/api/v1/applications/{$app->id}/move", [
        'stage_id' => $screeningStage->id,
    ]);

    Event::assertDispatched(App\Events\ApplicationStageChanged::class, function ($event) {
        return $event->data['notification_eligible'] === true;
    });
});
