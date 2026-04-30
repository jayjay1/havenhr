<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\CandidateEducation;
use App\Models\CandidateSkill;
use App\Models\CandidateWorkHistory;
use App\Models\Resume;
use App\Models\ResumeVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ResumeService
{
    /**
     * The PDF export service instance.
     */
    protected ?PDFExportService $pdfExportService = null;

    /**
     * Set the PDF export service.
     */
    public function setPdfExportService(PDFExportService $pdfExportService): void
    {
        $this->pdfExportService = $pdfExportService;
    }

    /**
     * Get the PDF export service (lazy-loaded).
     */
    protected function getPdfExportService(): PDFExportService
    {
        if ($this->pdfExportService === null) {
            $this->pdfExportService = app(PDFExportService::class);
        }

        return $this->pdfExportService;
    }

    /**
     * Maximum number of resumes a candidate can have.
     */
    public const MAX_RESUMES_PER_CANDIDATE = 20;

    /**
     * Maximum number of versions per resume.
     */
    public const MAX_VERSIONS_PER_RESUME = 50;

    /**
     * List all resumes for a candidate.
     *
     * @param  string  $candidateId
     * @return array<int, array<string, mixed>>
     */
    public function listResumes(string $candidateId): array
    {
        $resumes = Resume::where('candidate_id', $candidateId)
            ->orderByDesc('updated_at')
            ->get();

        return $resumes->map(fn (Resume $resume) => [
            'id' => $resume->id,
            'title' => $resume->title,
            'template_slug' => $resume->template_slug,
            'is_complete' => $resume->is_complete,
            'created_at' => $resume->created_at->toIso8601String(),
            'updated_at' => $resume->updated_at->toIso8601String(),
        ])->values()->toArray();
    }

    /**
     * Create a new resume for a candidate.
     * Enforces max 20 resumes per candidate.
     * Pre-populates content from candidate profile.
     *
     * @param  string  $candidateId
     * @param  array<string, mixed>  $data  Must include 'title' and 'template_slug'
     * @return Resume
     *
     * @throws \RuntimeException if max resumes limit reached
     */
    public function createResume(string $candidateId, array $data): Resume
    {
        $count = Resume::where('candidate_id', $candidateId)->count();

        if ($count >= self::MAX_RESUMES_PER_CANDIDATE) {
            throw new \RuntimeException(
                'Maximum number of resumes (' . self::MAX_RESUMES_PER_CANDIDATE . ') reached.'
            );
        }

        // Pre-populate content from candidate profile
        $content = $this->buildContentFromProfile($candidateId);

        return Resume::create([
            'candidate_id' => $candidateId,
            'title' => $data['title'],
            'template_slug' => $data['template_slug'],
            'content' => $content,
            'is_complete' => false,
            'public_link_active' => false,
            'show_contact_on_public' => false,
        ]);
    }

    /**
     * Get a full resume with content, verifying it belongs to the candidate.
     *
     * @param  string  $candidateId
     * @param  string  $resumeId
     * @return Resume
     */
    public function getResume(string $candidateId, string $resumeId): Resume
    {
        return Resume::where('id', $resumeId)
            ->where('candidate_id', $candidateId)
            ->firstOrFail();
    }

    /**
     * Update resume content (auto-save).
     * Creates a resume_version snapshot.
     * Enforces max 50 versions per resume.
     *
     * @param  string  $candidateId
     * @param  string  $resumeId
     * @param  array<string, mixed>  $data
     * @return Resume
     *
     * @throws \RuntimeException if max versions limit reached
     */
    public function updateResume(string $candidateId, string $resumeId, array $data): Resume
    {
        return DB::transaction(function () use ($candidateId, $resumeId, $data) {
            $resume = Resume::where('id', $resumeId)
                ->where('candidate_id', $candidateId)
                ->firstOrFail();

            // Check version limit before creating a new version
            $versionCount = ResumeVersion::where('resume_id', $resumeId)->count();

            if ($versionCount >= self::MAX_VERSIONS_PER_RESUME) {
                throw new \RuntimeException(
                    'Maximum number of versions (' . self::MAX_VERSIONS_PER_RESUME . ') reached for this resume.'
                );
            }

            // Update resume fields
            if (isset($data['title'])) {
                $resume->title = $data['title'];
            }
            if (isset($data['template_slug'])) {
                $resume->template_slug = $data['template_slug'];
            }
            if (isset($data['content'])) {
                $resume->content = $data['content'];
            }

            $resume->save();

            // Create version snapshot
            $maxVersionNumber = ResumeVersion::where('resume_id', $resumeId)
                ->max('version_number') ?? 0;

            ResumeVersion::create([
                'resume_id' => $resumeId,
                'content' => $resume->content,
                'version_number' => $maxVersionNumber + 1,
                'change_summary' => $data['change_summary'] ?? null,
            ]);

            $resume->refresh();

            return $resume;
        });
    }

    /**
     * Delete a resume and all its versions.
     *
     * @param  string  $candidateId
     * @param  string  $resumeId
     */
    public function deleteResume(string $candidateId, string $resumeId): void
    {
        $resume = Resume::where('id', $resumeId)
            ->where('candidate_id', $candidateId)
            ->firstOrFail();

        DB::transaction(function () use ($resume) {
            ResumeVersion::where('resume_id', $resume->id)->delete();
            $resume->delete();
        });
    }

    /**
     * Finalize a resume: set is_complete=true, create initial version if none exists.
     *
     * @param  string  $candidateId
     * @param  string  $resumeId
     * @return Resume
     */
    public function finalizeResume(string $candidateId, string $resumeId): Resume
    {
        return DB::transaction(function () use ($candidateId, $resumeId) {
            $resume = Resume::where('id', $resumeId)
                ->where('candidate_id', $candidateId)
                ->firstOrFail();

            $resume->is_complete = true;
            $resume->save();

            // Create initial version if none exists
            $hasVersions = ResumeVersion::where('resume_id', $resumeId)->exists();

            if (! $hasVersions) {
                ResumeVersion::create([
                    'resume_id' => $resumeId,
                    'content' => $resume->content,
                    'version_number' => 1,
                    'change_summary' => 'Initial finalized version',
                ]);
            }

            $resume->refresh();

            return $resume;
        });
    }

    /**
     * List all versions for a resume, ordered by created_at DESC.
     *
     * @param  string  $candidateId
     * @param  string  $resumeId
     * @return array<int, array<string, mixed>>
     */
    public function listVersions(string $candidateId, string $resumeId): array
    {
        // Verify resume belongs to candidate
        Resume::where('id', $resumeId)
            ->where('candidate_id', $candidateId)
            ->firstOrFail();

        $versions = ResumeVersion::where('resume_id', $resumeId)
            ->orderByDesc('created_at')
            ->orderByDesc('version_number')
            ->get();

        return $versions->map(fn (ResumeVersion $version) => [
            'id' => $version->id,
            'version_number' => $version->version_number,
            'change_summary' => $version->change_summary,
            'created_at' => $version->created_at->toIso8601String(),
        ])->values()->toArray();
    }

    /**
     * Restore a version: create a new version with the restored content (preserve history).
     *
     * @param  string  $candidateId
     * @param  string  $resumeId
     * @param  string  $versionId
     * @return Resume
     *
     * @throws \RuntimeException if max versions limit reached
     */
    public function restoreVersion(string $candidateId, string $resumeId, string $versionId): Resume
    {
        return DB::transaction(function () use ($candidateId, $resumeId, $versionId) {
            $resume = Resume::where('id', $resumeId)
                ->where('candidate_id', $candidateId)
                ->firstOrFail();

            $version = ResumeVersion::where('id', $versionId)
                ->where('resume_id', $resumeId)
                ->firstOrFail();

            // Check version limit
            $versionCount = ResumeVersion::where('resume_id', $resumeId)->count();

            if ($versionCount >= self::MAX_VERSIONS_PER_RESUME) {
                throw new \RuntimeException(
                    'Maximum number of versions (' . self::MAX_VERSIONS_PER_RESUME . ') reached for this resume.'
                );
            }

            // Update resume content with restored version
            $resume->content = $version->content;
            $resume->save();

            // Create new version with restored content
            $maxVersionNumber = ResumeVersion::where('resume_id', $resumeId)
                ->max('version_number') ?? 0;

            ResumeVersion::create([
                'resume_id' => $resumeId,
                'content' => $version->content,
                'version_number' => $maxVersionNumber + 1,
                'change_summary' => "Restored from version {$version->version_number}",
            ]);

            $resume->refresh();

            return $resume;
        });
    }

    /**
     * Toggle public sharing for a resume.
     * Enable: generate UUID v4 token.
     * Disable: set public_link_active=false.
     * Re-enable: generate NEW token.
     *
     * @param  string  $candidateId
     * @param  string  $resumeId
     * @param  bool  $enable
     * @param  bool  $showContact
     * @return Resume
     */
    public function toggleSharing(string $candidateId, string $resumeId, bool $enable, bool $showContact = false): Resume
    {
        $resume = Resume::where('id', $resumeId)
            ->where('candidate_id', $candidateId)
            ->firstOrFail();

        if ($enable) {
            // Always generate a new token on enable (including re-enable)
            $resume->public_link_token = (string) Str::uuid();
            $resume->public_link_active = true;
            $resume->show_contact_on_public = $showContact;
        } else {
            $resume->public_link_active = false;
        }

        $resume->save();
        $resume->refresh();

        return $resume;
    }

    /**
     * Export resume as PDF using PDFExportService.
     *
     * @param  string  $candidateId
     * @param  string  $resumeId
     * @return array<string, mixed>
     *
     * @throws \RuntimeException if PDF generation fails
     */
    public function exportPdf(string $candidateId, string $resumeId): array
    {
        $resume = Resume::where('id', $resumeId)
            ->where('candidate_id', $candidateId)
            ->firstOrFail();

        $result = $this->getPdfExportService()->export($resume);

        return [
            'download_url' => $result['download_url'],
            'filename' => $result['filename'],
            'status' => 'completed',
        ];
    }

    /**
     * Build resume content JSON from candidate profile data.
     *
     * @param  string  $candidateId
     * @return array<string, mixed>
     */
    protected function buildContentFromProfile(string $candidateId): array
    {
        $candidate = Candidate::findOrFail($candidateId);

        $workHistory = CandidateWorkHistory::where('candidate_id', $candidateId)
            ->orderBy('sort_order')
            ->orderByDesc('start_date')
            ->get();

        $education = CandidateEducation::where('candidate_id', $candidateId)
            ->orderBy('sort_order')
            ->orderByDesc('start_date')
            ->get();

        $skills = CandidateSkill::where('candidate_id', $candidateId)
            ->orderBy('sort_order')
            ->get();

        return [
            'personal_info' => [
                'name' => $candidate->name,
                'email' => $candidate->email,
                'phone' => $candidate->phone ?? '',
                'location' => $candidate->location ?? '',
                'linkedin_url' => $candidate->linkedin_url ?? '',
                'portfolio_url' => $candidate->portfolio_url ?? '',
            ],
            'summary' => '',
            'work_experience' => $workHistory->map(fn (CandidateWorkHistory $entry) => [
                'job_title' => $entry->job_title,
                'company_name' => $entry->company_name,
                'start_date' => $entry->start_date?->format('Y-m'),
                'end_date' => $entry->end_date?->format('Y-m'),
                'bullets' => $entry->description ? [$entry->description] : [],
            ])->values()->toArray(),
            'education' => $education->map(fn (CandidateEducation $entry) => [
                'institution_name' => $entry->institution_name,
                'degree' => $entry->degree,
                'field_of_study' => $entry->field_of_study,
                'start_date' => $entry->start_date?->format('Y-m'),
                'end_date' => $entry->end_date?->format('Y-m'),
            ])->values()->toArray(),
            'skills' => $skills->pluck('name')->values()->toArray(),
        ];
    }
}
