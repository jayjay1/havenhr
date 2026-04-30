<?php

use App\Models\Candidate;
use App\Models\Resume;
use App\Services\PDFExportService;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;

/*
|--------------------------------------------------------------------------
| Helper: authenticate a candidate and return the Bearer token
|--------------------------------------------------------------------------
*/

function pdfAuthCandidate(Candidate $candidate): string
{
    $customClaims = ['role' => 'candidate'];

    return JWTAuth::claims($customClaims)->fromUser($candidate);
}

/*
|--------------------------------------------------------------------------
| Helper: build sample resume content
|--------------------------------------------------------------------------
*/

function sampleResumeContent(): array
{
    return [
        'personal_info' => [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '555-1234',
            'location' => 'Boston, MA',
            'linkedin_url' => 'https://linkedin.com/in/janedoe',
            'portfolio_url' => 'https://janedoe.dev',
        ],
        'summary' => 'Experienced software engineer with 8 years of expertise in full-stack development, specializing in PHP and JavaScript frameworks.',
        'work_experience' => [
            [
                'job_title' => 'Senior Software Engineer',
                'company_name' => 'Acme Corp',
                'start_date' => '2020-01',
                'end_date' => null,
                'bullets' => [
                    'Led a team of 5 engineers to deliver a microservices platform',
                    'Reduced API response times by 40% through query optimization',
                ],
            ],
            [
                'job_title' => 'Software Engineer',
                'company_name' => 'StartupCo',
                'start_date' => '2016-06',
                'end_date' => '2019-12',
                'bullets' => [
                    'Built RESTful APIs serving 10,000+ daily active users',
                    'Implemented CI/CD pipeline reducing deployment time by 60%',
                ],
            ],
        ],
        'education' => [
            [
                'institution_name' => 'MIT',
                'degree' => 'BS',
                'field_of_study' => 'Computer Science',
                'start_date' => '2012-09',
                'end_date' => '2016-05',
            ],
        ],
        'skills' => ['PHP', 'Laravel', 'JavaScript', 'React', 'PostgreSQL', 'Docker', 'AWS'],
    ];
}

/*
|--------------------------------------------------------------------------
| PDFExportService — Unit-level tests
|--------------------------------------------------------------------------
*/

it('generates a PDF file for the clean template', function () {
    Storage::fake('local');

    $candidate = Candidate::factory()->create();
    $resume = Resume::create([
        'candidate_id' => $candidate->id,
        'title' => 'Engineering Resume',
        'template_slug' => 'clean',
        'content' => sampleResumeContent(),
        'is_complete' => true,
    ]);

    $service = new PDFExportService();
    $result = $service->export($resume);

    expect($result)->toHaveKeys(['download_url', 'filename']);
    expect($result['download_url'])->toStartWith('resumes/');
    expect($result['filename'])->toEndWith('.pdf');

    Storage::disk('local')->assertExists($result['download_url']);
});

it('generates a PDF file for the modern template', function () {
    Storage::fake('local');

    $candidate = Candidate::factory()->create();
    $resume = Resume::create([
        'candidate_id' => $candidate->id,
        'title' => 'Modern Resume',
        'template_slug' => 'modern',
        'content' => sampleResumeContent(),
        'is_complete' => true,
    ]);

    $service = new PDFExportService();
    $result = $service->export($resume);

    expect($result)->toHaveKeys(['download_url', 'filename']);
    Storage::disk('local')->assertExists($result['download_url']);
});

it('generates a PDF file for the professional template', function () {
    Storage::fake('local');

    $candidate = Candidate::factory()->create();
    $resume = Resume::create([
        'candidate_id' => $candidate->id,
        'title' => 'Professional Resume',
        'template_slug' => 'professional',
        'content' => sampleResumeContent(),
        'is_complete' => true,
    ]);

    $service = new PDFExportService();
    $result = $service->export($resume);

    expect($result)->toHaveKeys(['download_url', 'filename']);
    Storage::disk('local')->assertExists($result['download_url']);
});

it('generates a PDF file for the creative template', function () {
    Storage::fake('local');

    $candidate = Candidate::factory()->create();
    $resume = Resume::create([
        'candidate_id' => $candidate->id,
        'title' => 'Creative Resume',
        'template_slug' => 'creative',
        'content' => sampleResumeContent(),
        'is_complete' => true,
    ]);

    $service = new PDFExportService();
    $result = $service->export($resume);

    expect($result)->toHaveKeys(['download_url', 'filename']);
    Storage::disk('local')->assertExists($result['download_url']);
});

it('falls back to clean template for invalid template slug', function () {
    Storage::fake('local');

    $candidate = Candidate::factory()->create();
    $resume = Resume::create([
        'candidate_id' => $candidate->id,
        'title' => 'Fallback Resume',
        'template_slug' => 'nonexistent',
        'content' => sampleResumeContent(),
        'is_complete' => true,
    ]);

    $service = new PDFExportService();
    $result = $service->export($resume);

    expect($result)->toHaveKeys(['download_url', 'filename']);
    Storage::disk('local')->assertExists($result['download_url']);
});

it('handles resume with minimal content', function () {
    Storage::fake('local');

    $candidate = Candidate::factory()->create();
    $resume = Resume::create([
        'candidate_id' => $candidate->id,
        'title' => 'Minimal Resume',
        'template_slug' => 'clean',
        'content' => [
            'personal_info' => [
                'name' => 'John',
                'email' => '',
                'phone' => '',
                'location' => '',
                'linkedin_url' => '',
                'portfolio_url' => '',
            ],
            'summary' => '',
            'work_experience' => [],
            'education' => [],
            'skills' => [],
        ],
        'is_complete' => false,
    ]);

    $service = new PDFExportService();
    $result = $service->export($resume);

    expect($result)->toHaveKeys(['download_url', 'filename']);
    Storage::disk('local')->assertExists($result['download_url']);
});

/*
|--------------------------------------------------------------------------
| API endpoint — POST /api/v1/candidate/resumes/{id}/export-pdf
|--------------------------------------------------------------------------
*/

it('exports a PDF via the API endpoint and returns download URL', function () {
    Storage::fake('local');

    $candidate = Candidate::factory()->create();
    $resume = Resume::create([
        'candidate_id' => $candidate->id,
        'title' => 'API Export Test',
        'template_slug' => 'clean',
        'content' => sampleResumeContent(),
        'is_complete' => true,
    ]);

    $token = pdfAuthCandidate($candidate);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson("/api/v1/candidate/resumes/{$resume->id}/export-pdf");

    $response->assertStatus(200);
    expect($response->json('data.status'))->toBe('completed');
    expect($response->json('data.download_url'))->toStartWith('resumes/');
    expect($response->json('data.filename'))->toEndWith('.pdf');
});

it('returns 404 when exporting PDF for another candidate resume', function () {
    $candidate1 = Candidate::factory()->create();
    $candidate2 = Candidate::factory()->create();

    $resume = Resume::create([
        'candidate_id' => $candidate2->id,
        'title' => 'Other Resume',
        'template_slug' => 'clean',
        'content' => sampleResumeContent(),
        'is_complete' => true,
    ]);

    $token = pdfAuthCandidate($candidate1);

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson("/api/v1/candidate/resumes/{$resume->id}/export-pdf");

    $response->assertStatus(404);
});
