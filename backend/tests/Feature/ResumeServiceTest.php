<?php

use App\Models\Candidate;
use App\Models\CandidateEducation;
use App\Models\CandidateSkill;
use App\Models\CandidateWorkHistory;
use App\Models\Resume;
use App\Models\ResumeVersion;
use Tymon\JWTAuth\Facades\JWTAuth;

/*
|--------------------------------------------------------------------------
| Helper: authenticate a candidate and return the Bearer token
|--------------------------------------------------------------------------
*/

function authCandidate(Candidate $candidate): string
{
    $customClaims = ['role' => 'candidate'];

    return JWTAuth::claims($customClaims)->fromUser($candidate);
}

/*
|--------------------------------------------------------------------------
| POST /api/v1/candidate/resumes — Create Resume
|--------------------------------------------------------------------------
*/

it('creates a new resume with pre-populated content from profile', function () {
    $candidate = Candidate::factory()->create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '555-1234',
        'location' => 'Boston',
        'linkedin_url' => 'https://linkedin.com/in/jane',
        'portfolio_url' => 'https://jane.dev',
    ]);

    CandidateWorkHistory::create([
        'candidate_id' => $candidate->id,
        'job_title' => 'Engineer',
        'company_name' => 'Acme',
        'start_date' => '2020-01-01',
        'end_date' => null,
        'description' => 'Built things',
        'sort_order' => 0,
    ]);

    CandidateEducation::create([
        'candidate_id' => $candidate->id,
        'institution_name' => 'MIT',
        'degree' => 'BS',
        'field_of_study' => 'CS',
        'start_date' => '2014-09-01',
        'end_date' => '2018-06-01',
        'sort_order' => 0,
    ]);

    CandidateSkill::create([
        'candidate_id' => $candidate->id,
        'name' => 'PHP',
        'category' => 'technical',
        'sort_order' => 0,
    ]);

    $token = authCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/v1/candidate/resumes', [
        'title' => 'Engineering Resume',
        'template_slug' => 'modern',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => [
                'id', 'title', 'template_slug', 'content', 'is_complete',
                'public_link_token', 'public_link_active', 'show_contact_on_public',
                'created_at', 'updated_at',
            ],
        ]);

    expect($response->json('data.title'))->toBe('Engineering Resume');
    expect($response->json('data.template_slug'))->toBe('modern');
    expect($response->json('data.is_complete'))->toBeFalse();

    // Verify pre-populated content
    $content = $response->json('data.content');
    expect($content['personal_info']['name'])->toBe('Jane Doe');
    expect($content['personal_info']['email'])->toBe('jane@example.com');
    expect($content['personal_info']['phone'])->toBe('555-1234');
    expect($content['work_experience'])->toHaveCount(1);
    expect($content['work_experience'][0]['job_title'])->toBe('Engineer');
    expect($content['education'])->toHaveCount(1);
    expect($content['education'][0]['institution_name'])->toBe('MIT');
    expect($content['skills'])->toContain('PHP');
});

it('returns 422 when creating resume with missing required fields', function () {
    $candidate = Candidate::factory()->create();
    $token = authCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/v1/candidate/resumes', []);

    $response->assertStatus(422);
});

it('returns 422 when creating resume with invalid template_slug', function () {
    $candidate = Candidate::factory()->create();
    $token = authCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/v1/candidate/resumes', [
        'title' => 'My Resume',
        'template_slug' => 'invalid_template',
    ]);

    $response->assertStatus(422);
});

/*
|--------------------------------------------------------------------------
| Max 20 resumes per candidate
|--------------------------------------------------------------------------
*/

it('enforces max 20 resumes per candidate', function () {
    $candidate = Candidate::factory()->create();
    $token = authCandidate($candidate);

    // Create 20 resumes directly
    for ($i = 0; $i < 20; $i++) {
        Resume::create([
            'candidate_id' => $candidate->id,
            'title' => "Resume {$i}",
            'template_slug' => 'clean',
            'content' => ['personal_info' => ['name' => 'Test']],
            'is_complete' => false,
        ]);
    }

    // 21st should fail
    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/v1/candidate/resumes', [
        'title' => 'One Too Many',
        'template_slug' => 'clean',
    ]);

    $response->assertStatus(422);
    expect($response->json('error.code'))->toBe('LIMIT_EXCEEDED');
});

/*
|--------------------------------------------------------------------------
| GET /api/v1/candidate/resumes — List Resumes
|--------------------------------------------------------------------------
*/

it('lists all resumes for the authenticated candidate', function () {
    $candidate = Candidate::factory()->create();

    Resume::create([
        'candidate_id' => $candidate->id,
        'title' => 'Resume A',
        'template_slug' => 'clean',
        'content' => [],
        'is_complete' => false,
    ]);

    Resume::create([
        'candidate_id' => $candidate->id,
        'title' => 'Resume B',
        'template_slug' => 'modern',
        'content' => [],
        'is_complete' => true,
    ]);

    $token = authCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->getJson('/api/v1/candidate/resumes');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(2);
    expect($response->json('data.0'))->toHaveKeys(['id', 'title', 'template_slug', 'is_complete', 'created_at', 'updated_at']);
});

it('does not list resumes from other candidates', function () {
    $candidate1 = Candidate::factory()->create();
    $candidate2 = Candidate::factory()->create();

    Resume::create([
        'candidate_id' => $candidate2->id,
        'title' => 'Other Resume',
        'template_slug' => 'clean',
        'content' => [],
        'is_complete' => false,
    ]);

    $token = authCandidate($candidate1);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->getJson('/api/v1/candidate/resumes');

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(0);
});

/*
|--------------------------------------------------------------------------
| GET /api/v1/candidate/resumes/{id} — Show Resume
|--------------------------------------------------------------------------
*/

it('returns a single resume with full content', function () {
    $candidate = Candidate::factory()->create();

    $resume = Resume::create([
        'candidate_id' => $candidate->id,
        'title' => 'My Resume',
        'template_slug' => 'professional',
        'content' => ['personal_info' => ['name' => 'Test'], 'summary' => 'A summary'],
        'is_complete' => false,
    ]);

    $token = authCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->getJson("/api/v1/candidate/resumes/{$resume->id}");

    $response->assertStatus(200);
    expect($response->json('data.id'))->toBe($resume->id);
    expect($response->json('data.content.summary'))->toBe('A summary');
});

it('returns 404 when accessing another candidate resume', function () {
    $candidate1 = Candidate::factory()->create();
    $candidate2 = Candidate::factory()->create();

    $resume = Resume::create([
        'candidate_id' => $candidate2->id,
        'title' => 'Private Resume',
        'template_slug' => 'clean',
        'content' => [],
        'is_complete' => false,
    ]);

    $token = authCandidate($candidate1);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->getJson("/api/v1/candidate/resumes/{$resume->id}");

    $response->assertStatus(404);
});

/*
|--------------------------------------------------------------------------
| PUT /api/v1/candidate/resumes/{id} — Update Resume (auto-save)
|--------------------------------------------------------------------------
*/

it('updates resume content and creates a version snapshot', function () {
    $candidate = Candidate::factory()->create();

    $resume = Resume::create([
        'candidate_id' => $candidate->id,
        'title' => 'Original Title',
        'template_slug' => 'clean',
        'content' => ['summary' => 'Old summary'],
        'is_complete' => false,
    ]);

    $token = authCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->putJson("/api/v1/candidate/resumes/{$resume->id}", [
        'title' => 'Updated Title',
        'content' => ['summary' => 'New summary'],
        'change_summary' => 'Updated summary text',
    ]);

    $response->assertStatus(200);
    expect($response->json('data.title'))->toBe('Updated Title');
    expect($response->json('data.content.summary'))->toBe('New summary');

    // Verify version was created
    $versions = ResumeVersion::where('resume_id', $resume->id)->get();
    expect($versions)->toHaveCount(1);
    expect($versions->first()->version_number)->toBe(1);
    expect($versions->first()->change_summary)->toBe('Updated summary text');
    expect($versions->first()->content['summary'])->toBe('New summary');
});

it('enforces max 50 versions per resume', function () {
    $candidate = Candidate::factory()->create();

    $resume = Resume::create([
        'candidate_id' => $candidate->id,
        'title' => 'Version Test',
        'template_slug' => 'clean',
        'content' => ['summary' => 'test'],
        'is_complete' => false,
    ]);

    // Create 50 versions directly
    for ($i = 1; $i <= 50; $i++) {
        ResumeVersion::create([
            'resume_id' => $resume->id,
            'content' => ['summary' => "version {$i}"],
            'version_number' => $i,
        ]);
    }

    $token = authCandidate($candidate);

    // 51st version via update should fail
    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->putJson("/api/v1/candidate/resumes/{$resume->id}", [
        'content' => ['summary' => 'one too many'],
    ]);

    $response->assertStatus(422);
    expect($response->json('error.code'))->toBe('LIMIT_EXCEEDED');
});

/*
|--------------------------------------------------------------------------
| DELETE /api/v1/candidate/resumes/{id} — Delete Resume
|--------------------------------------------------------------------------
*/

it('deletes a resume and all its versions', function () {
    $candidate = Candidate::factory()->create();

    $resume = Resume::create([
        'candidate_id' => $candidate->id,
        'title' => 'To Delete',
        'template_slug' => 'clean',
        'content' => [],
        'is_complete' => false,
    ]);

    ResumeVersion::create([
        'resume_id' => $resume->id,
        'content' => [],
        'version_number' => 1,
    ]);

    $token = authCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->deleteJson("/api/v1/candidate/resumes/{$resume->id}");

    $response->assertStatus(200);
    expect(Resume::find($resume->id))->toBeNull();
    expect(ResumeVersion::where('resume_id', $resume->id)->count())->toBe(0);
});

it('returns 404 when deleting another candidate resume', function () {
    $candidate1 = Candidate::factory()->create();
    $candidate2 = Candidate::factory()->create();

    $resume = Resume::create([
        'candidate_id' => $candidate2->id,
        'title' => 'Protected',
        'template_slug' => 'clean',
        'content' => [],
        'is_complete' => false,
    ]);

    $token = authCandidate($candidate1);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->deleteJson("/api/v1/candidate/resumes/{$resume->id}");

    $response->assertStatus(404);
    expect(Resume::find($resume->id))->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| POST /api/v1/candidate/resumes/{id}/finalize — Finalize Resume
|--------------------------------------------------------------------------
*/

it('finalizes a resume and creates initial version if none exists', function () {
    $candidate = Candidate::factory()->create();

    $resume = Resume::create([
        'candidate_id' => $candidate->id,
        'title' => 'Draft Resume',
        'template_slug' => 'clean',
        'content' => ['summary' => 'My summary'],
        'is_complete' => false,
    ]);

    $token = authCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson("/api/v1/candidate/resumes/{$resume->id}/finalize");

    $response->assertStatus(200);
    expect($response->json('data.is_complete'))->toBeTrue();

    // Verify initial version was created
    $versions = ResumeVersion::where('resume_id', $resume->id)->get();
    expect($versions)->toHaveCount(1);
    expect($versions->first()->version_number)->toBe(1);
    expect($versions->first()->change_summary)->toBe('Initial finalized version');
});

it('does not create duplicate initial version on finalize if versions exist', function () {
    $candidate = Candidate::factory()->create();

    $resume = Resume::create([
        'candidate_id' => $candidate->id,
        'title' => 'Has Versions',
        'template_slug' => 'clean',
        'content' => ['summary' => 'test'],
        'is_complete' => false,
    ]);

    ResumeVersion::create([
        'resume_id' => $resume->id,
        'content' => ['summary' => 'test'],
        'version_number' => 1,
    ]);

    $token = authCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson("/api/v1/candidate/resumes/{$resume->id}/finalize");

    $response->assertStatus(200);
    expect($response->json('data.is_complete'))->toBeTrue();

    // Should still have only 1 version
    expect(ResumeVersion::where('resume_id', $resume->id)->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| GET /api/v1/candidate/resumes/{id}/versions — List Versions
|--------------------------------------------------------------------------
*/

it('lists versions ordered by created_at DESC', function () {
    $candidate = Candidate::factory()->create();

    $resume = Resume::create([
        'candidate_id' => $candidate->id,
        'title' => 'Versioned Resume',
        'template_slug' => 'clean',
        'content' => [],
        'is_complete' => false,
    ]);

    ResumeVersion::create([
        'resume_id' => $resume->id,
        'content' => ['summary' => 'v1'],
        'version_number' => 1,
        'change_summary' => 'First version',
    ]);

    ResumeVersion::create([
        'resume_id' => $resume->id,
        'content' => ['summary' => 'v2'],
        'version_number' => 2,
        'change_summary' => 'Second version',
    ]);

    $token = authCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->getJson("/api/v1/candidate/resumes/{$resume->id}/versions");

    $response->assertStatus(200);
    $versions = $response->json('data');
    expect($versions)->toHaveCount(2);
    // Most recent first
    expect($versions[0]['version_number'])->toBe(2);
    expect($versions[1]['version_number'])->toBe(1);
});

/*
|--------------------------------------------------------------------------
| POST /api/v1/candidate/resumes/{id}/versions/{versionId}/restore
|--------------------------------------------------------------------------
*/

it('restores a version by creating a new version with restored content', function () {
    $candidate = Candidate::factory()->create();

    $resume = Resume::create([
        'candidate_id' => $candidate->id,
        'title' => 'Restore Test',
        'template_slug' => 'clean',
        'content' => ['summary' => 'current'],
        'is_complete' => false,
    ]);

    $v1 = ResumeVersion::create([
        'resume_id' => $resume->id,
        'content' => ['summary' => 'original content'],
        'version_number' => 1,
    ]);

    ResumeVersion::create([
        'resume_id' => $resume->id,
        'content' => ['summary' => 'current'],
        'version_number' => 2,
    ]);

    $token = authCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson("/api/v1/candidate/resumes/{$resume->id}/versions/{$v1->id}/restore");

    $response->assertStatus(200);
    expect($response->json('data.content.summary'))->toBe('original content');

    // Should now have 3 versions (original 2 + restored)
    $versions = ResumeVersion::where('resume_id', $resume->id)->orderBy('version_number')->get();
    expect($versions)->toHaveCount(3);
    expect($versions->last()->version_number)->toBe(3);
    expect($versions->last()->content['summary'])->toBe('original content');
    expect($versions->last()->change_summary)->toBe('Restored from version 1');
});

/*
|--------------------------------------------------------------------------
| POST /api/v1/candidate/resumes/{id}/share — Toggle Sharing
|--------------------------------------------------------------------------
*/

it('enables public sharing and generates a token', function () {
    $candidate = Candidate::factory()->create();

    $resume = Resume::create([
        'candidate_id' => $candidate->id,
        'title' => 'Share Test',
        'template_slug' => 'clean',
        'content' => [],
        'is_complete' => true,
    ]);

    $token = authCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson("/api/v1/candidate/resumes/{$resume->id}/share", [
        'enable' => true,
        'show_contact' => false,
    ]);

    $response->assertStatus(200);
    expect($response->json('data.public_link_active'))->toBeTrue();
    expect($response->json('data.public_link_token'))->not->toBeNull();
    expect($response->json('data.show_contact_on_public'))->toBeFalse();
});

it('disables public sharing', function () {
    $candidate = Candidate::factory()->create();

    $resume = Resume::create([
        'candidate_id' => $candidate->id,
        'title' => 'Share Test',
        'template_slug' => 'clean',
        'content' => [],
        'is_complete' => true,
        'public_link_token' => (string) \Illuminate\Support\Str::uuid(),
        'public_link_active' => true,
    ]);

    $token = authCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson("/api/v1/candidate/resumes/{$resume->id}/share", [
        'enable' => false,
    ]);

    $response->assertStatus(200);
    expect($response->json('data.public_link_active'))->toBeFalse();
});

it('generates a new token on re-enable', function () {
    $candidate = Candidate::factory()->create();
    $oldToken = (string) \Illuminate\Support\Str::uuid();

    $resume = Resume::create([
        'candidate_id' => $candidate->id,
        'title' => 'Re-enable Test',
        'template_slug' => 'clean',
        'content' => [],
        'is_complete' => true,
        'public_link_token' => $oldToken,
        'public_link_active' => false,
    ]);

    $token = authCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson("/api/v1/candidate/resumes/{$resume->id}/share", [
        'enable' => true,
    ]);

    $response->assertStatus(200);
    expect($response->json('data.public_link_active'))->toBeTrue();
    $newPublicToken = $response->json('data.public_link_token');
    expect($newPublicToken)->not->toBeNull();
    expect($newPublicToken)->not->toBe($oldToken);
});

/*
|--------------------------------------------------------------------------
| POST /api/v1/candidate/resumes/{id}/export-pdf — Export PDF (placeholder)
|--------------------------------------------------------------------------
*/

it('exports a PDF and returns download URL', function () {
    \Illuminate\Support\Facades\Storage::fake('local');

    $candidate = Candidate::factory()->create();

    $resume = Resume::create([
        'candidate_id' => $candidate->id,
        'title' => 'PDF Test',
        'template_slug' => 'clean',
        'content' => [
            'personal_info' => [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'phone' => '',
                'location' => '',
                'linkedin_url' => '',
                'portfolio_url' => '',
            ],
            'summary' => 'A test summary.',
            'work_experience' => [],
            'education' => [],
            'skills' => [],
        ],
        'is_complete' => true,
    ]);

    $token = authCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson("/api/v1/candidate/resumes/{$resume->id}/export-pdf");

    $response->assertStatus(200);
    expect($response->json('data.status'))->toBe('completed');
    expect($response->json('data.download_url'))->toStartWith('resumes/');
});

/*
|--------------------------------------------------------------------------
| Authentication required
|--------------------------------------------------------------------------
*/

it('returns 401 when accessing resumes without authentication', function () {
    $response = $this->getJson('/api/v1/candidate/resumes');
    $response->assertStatus(401);
});

it('returns 401 when creating resume without authentication', function () {
    $response = $this->postJson('/api/v1/candidate/resumes', [
        'title' => 'Test',
        'template_slug' => 'clean',
    ]);
    $response->assertStatus(401);
});
