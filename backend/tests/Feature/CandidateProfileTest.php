<?php

use App\Models\Candidate;
use App\Models\CandidateEducation;
use App\Models\CandidateSkill;
use App\Models\CandidateWorkHistory;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

/*
|--------------------------------------------------------------------------
| Helper: authenticate a candidate and return the Bearer token
|--------------------------------------------------------------------------
*/

function authenticateCandidate(Candidate $candidate): string
{
    $customClaims = ['role' => 'candidate'];

    return JWTAuth::claims($customClaims)->fromUser($candidate);
}

/*
|--------------------------------------------------------------------------
| GET /api/v1/candidate/profile — Get Full Profile
|--------------------------------------------------------------------------
*/

it('returns the full candidate profile with all sections', function () {
    $candidate = Candidate::factory()->create([
        'name' => 'Alice Smith',
        'email' => 'alice@example.com',
        'phone' => '555-0100',
        'location' => 'San Francisco',
        'linkedin_url' => 'https://linkedin.com/in/alice',
        'portfolio_url' => 'https://alice.dev',
    ]);

    CandidateWorkHistory::create([
        'candidate_id' => $candidate->id,
        'job_title' => 'Senior Engineer',
        'company_name' => 'Acme Corp',
        'start_date' => '2022-01-01',
        'end_date' => null,
        'description' => 'Led backend team',
        'sort_order' => 0,
    ]);

    CandidateEducation::create([
        'candidate_id' => $candidate->id,
        'institution_name' => 'MIT',
        'degree' => 'BS',
        'field_of_study' => 'Computer Science',
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

    $token = authenticateCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->getJson('/api/v1/candidate/profile');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'id', 'name', 'email', 'phone', 'location',
                'linkedin_url', 'portfolio_url',
                'work_history' => [['id', 'job_title', 'company_name', 'start_date', 'end_date', 'description', 'sort_order']],
                'education' => [['id', 'institution_name', 'degree', 'field_of_study', 'start_date', 'end_date', 'sort_order']],
                'skills' => [['id', 'name', 'category', 'sort_order']],
            ],
        ]);

    expect($response->json('data.name'))->toBe('Alice Smith');
    expect($response->json('data.work_history'))->toHaveCount(1);
    expect($response->json('data.education'))->toHaveCount(1);
    expect($response->json('data.skills'))->toHaveCount(1);
});

it('returns empty collections for a new candidate profile', function () {
    $candidate = Candidate::factory()->create();
    $token = authenticateCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->getJson('/api/v1/candidate/profile');

    $response->assertStatus(200);
    expect($response->json('data.work_history'))->toBeArray()->toBeEmpty();
    expect($response->json('data.education'))->toBeArray()->toBeEmpty();
    expect($response->json('data.skills'))->toBeArray()->toBeEmpty();
});

it('returns 401 when accessing profile without authentication', function () {
    $response = $this->getJson('/api/v1/candidate/profile');

    $response->assertStatus(401);
});

/*
|--------------------------------------------------------------------------
| PUT /api/v1/candidate/profile — Update Personal Info
|--------------------------------------------------------------------------
*/

it('updates candidate personal info', function () {
    $candidate = Candidate::factory()->create();
    $token = authenticateCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->putJson('/api/v1/candidate/profile', [
        'name' => 'Updated Name',
        'phone' => '555-9999',
        'location' => 'New York',
        'linkedin_url' => 'https://linkedin.com/in/updated',
        'portfolio_url' => 'https://updated.dev',
    ]);

    $response->assertStatus(200);
    expect($response->json('data.name'))->toBe('Updated Name');
    expect($response->json('data.phone'))->toBe('555-9999');
    expect($response->json('data.location'))->toBe('New York');
    expect($response->json('data.linkedin_url'))->toBe('https://linkedin.com/in/updated');
    expect($response->json('data.portfolio_url'))->toBe('https://updated.dev');

    // Verify persisted in DB
    $candidate->refresh();
    expect($candidate->name)->toBe('Updated Name');
});

it('allows partial personal info updates', function () {
    $candidate = Candidate::factory()->create([
        'name' => 'Original Name',
        'phone' => '555-0000',
    ]);
    $token = authenticateCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->putJson('/api/v1/candidate/profile', [
        'phone' => '555-1111',
    ]);

    $response->assertStatus(200);
    expect($response->json('data.name'))->toBe('Original Name');
    expect($response->json('data.phone'))->toBe('555-1111');
});

it('rejects invalid linkedin_url format', function () {
    $candidate = Candidate::factory()->create();
    $token = authenticateCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->putJson('/api/v1/candidate/profile', [
        'linkedin_url' => 'not-a-url',
    ]);

    $response->assertStatus(422);
});

/*
|--------------------------------------------------------------------------
| POST /api/v1/candidate/profile/work-history — Add Work History
|--------------------------------------------------------------------------
*/

it('adds a work history entry', function () {
    $candidate = Candidate::factory()->create();
    $token = authenticateCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/v1/candidate/profile/work-history', [
        'job_title' => 'Software Engineer',
        'company_name' => 'Tech Corp',
        'start_date' => '2020-01',
        'end_date' => '2023-06',
        'description' => 'Built APIs',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => ['id', 'job_title', 'company_name', 'start_date', 'end_date', 'description', 'sort_order'],
        ]);

    expect($response->json('data.job_title'))->toBe('Software Engineer');
    expect($response->json('data.start_date'))->toBe('2020-01');
    expect($response->json('data.end_date'))->toBe('2023-06');
    expect($response->json('data.sort_order'))->toBe(0);

    // Verify in DB
    expect(CandidateWorkHistory::where('candidate_id', $candidate->id)->count())->toBe(1);
});

it('allows null end_date for current position', function () {
    $candidate = Candidate::factory()->create();
    $token = authenticateCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/v1/candidate/profile/work-history', [
        'job_title' => 'CTO',
        'company_name' => 'Startup Inc',
        'start_date' => '2023-01',
    ]);

    $response->assertStatus(201);
    expect($response->json('data.end_date'))->toBeNull();
});

it('rejects work history with missing required fields', function () {
    $candidate = Candidate::factory()->create();
    $token = authenticateCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/v1/candidate/profile/work-history', []);

    $response->assertStatus(422);
    expect($response->json('error.code'))->toBe('VALIDATION_ERROR');
});

/*
|--------------------------------------------------------------------------
| PUT /api/v1/candidate/profile/work-history/{id} — Update Work History
|--------------------------------------------------------------------------
*/

it('updates a work history entry', function () {
    $candidate = Candidate::factory()->create();
    $entry = CandidateWorkHistory::create([
        'candidate_id' => $candidate->id,
        'job_title' => 'Junior Dev',
        'company_name' => 'Old Corp',
        'start_date' => '2019-01-01',
        'description' => 'Old description',
        'sort_order' => 0,
    ]);

    $token = authenticateCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->putJson("/api/v1/candidate/profile/work-history/{$entry->id}", [
        'job_title' => 'Senior Dev',
        'description' => 'New description',
    ]);

    $response->assertStatus(200);
    expect($response->json('data.job_title'))->toBe('Senior Dev');
    expect($response->json('data.description'))->toBe('New description');
    expect($response->json('data.company_name'))->toBe('Old Corp');
});

it('returns 404 when updating another candidate work history', function () {
    $candidate1 = Candidate::factory()->create();
    $candidate2 = Candidate::factory()->create();

    $entry = CandidateWorkHistory::create([
        'candidate_id' => $candidate2->id,
        'job_title' => 'Engineer',
        'company_name' => 'Other Corp',
        'start_date' => '2020-01-01',
        'description' => 'Work',
        'sort_order' => 0,
    ]);

    $token = authenticateCandidate($candidate1);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->putJson("/api/v1/candidate/profile/work-history/{$entry->id}", [
        'job_title' => 'Hacked',
    ]);

    $response->assertStatus(404);
});

/*
|--------------------------------------------------------------------------
| DELETE /api/v1/candidate/profile/work-history/{id} — Delete Work History
|--------------------------------------------------------------------------
*/

it('deletes a work history entry', function () {
    $candidate = Candidate::factory()->create();
    $entry = CandidateWorkHistory::create([
        'candidate_id' => $candidate->id,
        'job_title' => 'To Delete',
        'company_name' => 'Corp',
        'start_date' => '2020-01-01',
        'description' => '',
        'sort_order' => 0,
    ]);

    $token = authenticateCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->deleteJson("/api/v1/candidate/profile/work-history/{$entry->id}");

    $response->assertStatus(200);
    expect(CandidateWorkHistory::find($entry->id))->toBeNull();
});

it('returns 404 when deleting another candidate work history', function () {
    $candidate1 = Candidate::factory()->create();
    $candidate2 = Candidate::factory()->create();

    $entry = CandidateWorkHistory::create([
        'candidate_id' => $candidate2->id,
        'job_title' => 'Protected',
        'company_name' => 'Corp',
        'start_date' => '2020-01-01',
        'description' => '',
        'sort_order' => 0,
    ]);

    $token = authenticateCandidate($candidate1);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->deleteJson("/api/v1/candidate/profile/work-history/{$entry->id}");

    $response->assertStatus(404);
    // Entry should still exist
    expect(CandidateWorkHistory::find($entry->id))->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| PUT /api/v1/candidate/profile/work-history/reorder — Reorder Work History
|--------------------------------------------------------------------------
*/

it('reorders work history entries', function () {
    $candidate = Candidate::factory()->create();

    $entry1 = CandidateWorkHistory::create([
        'candidate_id' => $candidate->id,
        'job_title' => 'First',
        'company_name' => 'Corp A',
        'start_date' => '2020-01-01',
        'description' => '',
        'sort_order' => 0,
    ]);

    $entry2 = CandidateWorkHistory::create([
        'candidate_id' => $candidate->id,
        'job_title' => 'Second',
        'company_name' => 'Corp B',
        'start_date' => '2021-01-01',
        'description' => '',
        'sort_order' => 1,
    ]);

    $token = authenticateCandidate($candidate);

    // Reverse the order
    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->putJson('/api/v1/candidate/profile/work-history/reorder', [
        'ordered_ids' => [$entry2->id, $entry1->id],
    ]);

    $response->assertStatus(200);

    // Verify new order
    $entry1->refresh();
    $entry2->refresh();
    expect($entry2->sort_order)->toBe(0);
    expect($entry1->sort_order)->toBe(1);
});

/*
|--------------------------------------------------------------------------
| POST /api/v1/candidate/profile/education — Add Education
|--------------------------------------------------------------------------
*/

it('adds an education entry', function () {
    $candidate = Candidate::factory()->create();
    $token = authenticateCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/v1/candidate/profile/education', [
        'institution_name' => 'Stanford University',
        'degree' => 'MS',
        'field_of_study' => 'Computer Science',
        'start_date' => '2018-09',
        'end_date' => '2020-06',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'data' => ['id', 'institution_name', 'degree', 'field_of_study', 'start_date', 'end_date', 'sort_order'],
        ]);

    expect($response->json('data.institution_name'))->toBe('Stanford University');
    expect($response->json('data.degree'))->toBe('MS');
    expect($response->json('data.sort_order'))->toBe(0);
});

it('rejects education with missing required fields', function () {
    $candidate = Candidate::factory()->create();
    $token = authenticateCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/v1/candidate/profile/education', []);

    $response->assertStatus(422);
});

/*
|--------------------------------------------------------------------------
| PUT /api/v1/candidate/profile/education/{id} — Update Education
|--------------------------------------------------------------------------
*/

it('updates an education entry', function () {
    $candidate = Candidate::factory()->create();
    $entry = CandidateEducation::create([
        'candidate_id' => $candidate->id,
        'institution_name' => 'Old University',
        'degree' => 'BA',
        'field_of_study' => 'English',
        'start_date' => '2014-09-01',
        'end_date' => '2018-06-01',
        'sort_order' => 0,
    ]);

    $token = authenticateCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->putJson("/api/v1/candidate/profile/education/{$entry->id}", [
        'degree' => 'BS',
        'field_of_study' => 'Computer Science',
    ]);

    $response->assertStatus(200);
    expect($response->json('data.degree'))->toBe('BS');
    expect($response->json('data.field_of_study'))->toBe('Computer Science');
    expect($response->json('data.institution_name'))->toBe('Old University');
});

it('returns 404 when updating another candidate education', function () {
    $candidate1 = Candidate::factory()->create();
    $candidate2 = Candidate::factory()->create();

    $entry = CandidateEducation::create([
        'candidate_id' => $candidate2->id,
        'institution_name' => 'Private Uni',
        'degree' => 'PhD',
        'field_of_study' => 'Physics',
        'start_date' => '2010-09-01',
        'sort_order' => 0,
    ]);

    $token = authenticateCandidate($candidate1);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->putJson("/api/v1/candidate/profile/education/{$entry->id}", [
        'degree' => 'Hacked',
    ]);

    $response->assertStatus(404);
});

/*
|--------------------------------------------------------------------------
| DELETE /api/v1/candidate/profile/education/{id} — Delete Education
|--------------------------------------------------------------------------
*/

it('deletes an education entry', function () {
    $candidate = Candidate::factory()->create();
    $entry = CandidateEducation::create([
        'candidate_id' => $candidate->id,
        'institution_name' => 'To Delete Uni',
        'degree' => 'BS',
        'field_of_study' => 'Math',
        'start_date' => '2014-09-01',
        'sort_order' => 0,
    ]);

    $token = authenticateCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->deleteJson("/api/v1/candidate/profile/education/{$entry->id}");

    $response->assertStatus(200);
    expect(CandidateEducation::find($entry->id))->toBeNull();
});

/*
|--------------------------------------------------------------------------
| PUT /api/v1/candidate/profile/education/reorder — Reorder Education
|--------------------------------------------------------------------------
*/

it('reorders education entries', function () {
    $candidate = Candidate::factory()->create();

    $entry1 = CandidateEducation::create([
        'candidate_id' => $candidate->id,
        'institution_name' => 'First Uni',
        'degree' => 'BS',
        'field_of_study' => 'CS',
        'start_date' => '2014-09-01',
        'sort_order' => 0,
    ]);

    $entry2 = CandidateEducation::create([
        'candidate_id' => $candidate->id,
        'institution_name' => 'Second Uni',
        'degree' => 'MS',
        'field_of_study' => 'CS',
        'start_date' => '2018-09-01',
        'sort_order' => 1,
    ]);

    $token = authenticateCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->putJson('/api/v1/candidate/profile/education/reorder', [
        'ordered_ids' => [$entry2->id, $entry1->id],
    ]);

    $response->assertStatus(200);

    $entry1->refresh();
    $entry2->refresh();
    expect($entry2->sort_order)->toBe(0);
    expect($entry1->sort_order)->toBe(1);
});

/*
|--------------------------------------------------------------------------
| PUT /api/v1/candidate/profile/skills — Replace Skills
|--------------------------------------------------------------------------
*/

it('replaces all skills for a candidate', function () {
    $candidate = Candidate::factory()->create();

    // Create initial skills
    CandidateSkill::create([
        'candidate_id' => $candidate->id,
        'name' => 'Old Skill',
        'category' => 'technical',
        'sort_order' => 0,
    ]);

    $token = authenticateCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->putJson('/api/v1/candidate/profile/skills', [
        'skills' => [
            ['name' => 'PHP', 'category' => 'technical'],
            ['name' => 'Laravel', 'category' => 'technical'],
            ['name' => 'Communication', 'category' => 'soft'],
        ],
    ]);

    $response->assertStatus(200);
    $skills = $response->json('data.skills');
    expect($skills)->toHaveCount(3);
    expect($skills[0]['name'])->toBe('PHP');
    expect($skills[0]['category'])->toBe('technical');
    expect($skills[0]['sort_order'])->toBe(0);
    expect($skills[1]['name'])->toBe('Laravel');
    expect($skills[2]['name'])->toBe('Communication');
    expect($skills[2]['category'])->toBe('soft');

    // Old skill should be gone
    expect(CandidateSkill::where('candidate_id', $candidate->id)->count())->toBe(3);
    expect(CandidateSkill::where('candidate_id', $candidate->id)->where('name', 'Old Skill')->exists())->toBeFalse();
});

it('replaces skills with empty array to clear all skills', function () {
    $candidate = Candidate::factory()->create();

    CandidateSkill::create([
        'candidate_id' => $candidate->id,
        'name' => 'PHP',
        'category' => 'technical',
        'sort_order' => 0,
    ]);

    $token = authenticateCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->putJson('/api/v1/candidate/profile/skills', [
        'skills' => [],
    ]);

    $response->assertStatus(200);
    expect($response->json('data.skills'))->toBeArray()->toBeEmpty();
    expect(CandidateSkill::where('candidate_id', $candidate->id)->count())->toBe(0);
});

it('rejects skills with invalid category', function () {
    $candidate = Candidate::factory()->create();
    $token = authenticateCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->putJson('/api/v1/candidate/profile/skills', [
        'skills' => [
            ['name' => 'PHP', 'category' => 'invalid_category'],
        ],
    ]);

    $response->assertStatus(422);
});

/*
|--------------------------------------------------------------------------
| Auto-incrementing sort_order tests
|--------------------------------------------------------------------------
*/

it('auto-increments sort_order for new work history entries', function () {
    $candidate = Candidate::factory()->create();
    $token = authenticateCandidate($candidate);

    $response1 = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/v1/candidate/profile/work-history', [
        'job_title' => 'First Job',
        'company_name' => 'Corp A',
        'start_date' => '2020-01',
    ]);

    $response2 = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/v1/candidate/profile/work-history', [
        'job_title' => 'Second Job',
        'company_name' => 'Corp B',
        'start_date' => '2021-01',
    ]);

    expect($response1->json('data.sort_order'))->toBe(0);
    expect($response2->json('data.sort_order'))->toBe(1);
});

it('auto-increments sort_order for new education entries', function () {
    $candidate = Candidate::factory()->create();
    $token = authenticateCandidate($candidate);

    $response1 = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/v1/candidate/profile/education', [
        'institution_name' => 'Uni A',
        'degree' => 'BS',
        'field_of_study' => 'CS',
        'start_date' => '2014-09',
    ]);

    $response2 = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/v1/candidate/profile/education', [
        'institution_name' => 'Uni B',
        'degree' => 'MS',
        'field_of_study' => 'CS',
        'start_date' => '2018-09',
    ]);

    expect($response1->json('data.sort_order'))->toBe(0);
    expect($response2->json('data.sort_order'))->toBe(1);
});
