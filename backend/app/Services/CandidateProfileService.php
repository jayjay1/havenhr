<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\CandidateEducation;
use App\Models\CandidateSkill;
use App\Models\CandidateWorkHistory;
use Illuminate\Support\Facades\DB;

class CandidateProfileService
{
    /**
     * Get the full profile for a candidate, including personal info,
     * work history, education, and skills.
     *
     * @param  string  $candidateId
     * @return array<string, mixed>
     */
    public function getProfile(string $candidateId): array
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
            'id' => $candidate->id,
            'name' => $candidate->name,
            'email' => $candidate->email,
            'phone' => $candidate->phone,
            'location' => $candidate->location,
            'linkedin_url' => $candidate->linkedin_url,
            'portfolio_url' => $candidate->portfolio_url,
            'professional_summary' => $candidate->professional_summary,
            'github_url' => $candidate->github_url,
            'is_profile_public' => $candidate->is_profile_public,
            'work_history' => $workHistory->map(fn (CandidateWorkHistory $entry) => [
                'id' => $entry->id,
                'job_title' => $entry->job_title,
                'company_name' => $entry->company_name,
                'start_date' => $entry->start_date?->format('Y-m'),
                'end_date' => $entry->end_date?->format('Y-m'),
                'description' => $entry->description,
                'sort_order' => $entry->sort_order,
            ])->values()->toArray(),
            'education' => $education->map(fn (CandidateEducation $entry) => [
                'id' => $entry->id,
                'institution_name' => $entry->institution_name,
                'degree' => $entry->degree,
                'field_of_study' => $entry->field_of_study,
                'start_date' => $entry->start_date?->format('Y-m'),
                'end_date' => $entry->end_date?->format('Y-m'),
                'sort_order' => $entry->sort_order,
            ])->values()->toArray(),
            'skills' => $skills->map(fn (CandidateSkill $entry) => [
                'id' => $entry->id,
                'name' => $entry->name,
                'category' => $entry->category,
                'sort_order' => $entry->sort_order,
            ])->values()->toArray(),
        ];
    }

    /**
     * Update personal info fields for a candidate.
     *
     * @param  string  $candidateId
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updatePersonalInfo(string $candidateId, array $data): array
    {
        $candidate = Candidate::findOrFail($candidateId);

        $allowedFields = ['name', 'phone', 'location', 'linkedin_url', 'portfolio_url', 'professional_summary', 'github_url', 'is_profile_public'];
        $updateData = array_intersect_key($data, array_flip($allowedFields));

        $candidate->update($updateData);
        $candidate->refresh();

        return [
            'id' => $candidate->id,
            'name' => $candidate->name,
            'email' => $candidate->email,
            'phone' => $candidate->phone,
            'location' => $candidate->location,
            'linkedin_url' => $candidate->linkedin_url,
            'portfolio_url' => $candidate->portfolio_url,
            'professional_summary' => $candidate->professional_summary,
            'github_url' => $candidate->github_url,
            'is_profile_public' => $candidate->is_profile_public,
        ];
    }

    /**
     * Add a work history entry for a candidate.
     *
     * @param  string  $candidateId
     * @param  array<string, mixed>  $data
     * @return CandidateWorkHistory
     */
    public function addWorkHistory(string $candidateId, array $data): CandidateWorkHistory
    {
        // Ensure the candidate exists
        Candidate::findOrFail($candidateId);

        // Determine the next sort_order
        $maxSortOrder = CandidateWorkHistory::where('candidate_id', $candidateId)
            ->max('sort_order') ?? -1;

        return CandidateWorkHistory::create([
            'candidate_id' => $candidateId,
            'job_title' => $data['job_title'],
            'company_name' => $data['company_name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'description' => $data['description'] ?? '',
            'sort_order' => $maxSortOrder + 1,
        ]);
    }

    /**
     * Update a work history entry, verifying it belongs to the candidate.
     *
     * @param  string  $candidateId
     * @param  string  $entryId
     * @param  array<string, mixed>  $data
     * @return CandidateWorkHistory
     */
    public function updateWorkHistory(string $candidateId, string $entryId, array $data): CandidateWorkHistory
    {
        $entry = CandidateWorkHistory::where('id', $entryId)
            ->where('candidate_id', $candidateId)
            ->firstOrFail();

        $allowedFields = ['job_title', 'company_name', 'start_date', 'end_date', 'description'];
        $updateData = array_intersect_key($data, array_flip($allowedFields));

        $entry->update($updateData);
        $entry->refresh();

        return $entry;
    }

    /**
     * Delete a work history entry, verifying it belongs to the candidate.
     *
     * @param  string  $candidateId
     * @param  string  $entryId
     */
    public function deleteWorkHistory(string $candidateId, string $entryId): void
    {
        $entry = CandidateWorkHistory::where('id', $entryId)
            ->where('candidate_id', $candidateId)
            ->firstOrFail();

        $entry->delete();
    }

    /**
     * Reorder work history entries by setting sort_order based on the provided ordered IDs.
     *
     * @param  string  $candidateId
     * @param  array<int, string>  $orderedIds
     */
    public function reorderWorkHistory(string $candidateId, array $orderedIds): void
    {
        DB::transaction(function () use ($candidateId, $orderedIds) {
            foreach ($orderedIds as $index => $id) {
                CandidateWorkHistory::where('id', $id)
                    ->where('candidate_id', $candidateId)
                    ->update(['sort_order' => $index]);
            }
        });
    }

    /**
     * Add an education entry for a candidate.
     *
     * @param  string  $candidateId
     * @param  array<string, mixed>  $data
     * @return CandidateEducation
     */
    public function addEducation(string $candidateId, array $data): CandidateEducation
    {
        // Ensure the candidate exists
        Candidate::findOrFail($candidateId);

        // Determine the next sort_order
        $maxSortOrder = CandidateEducation::where('candidate_id', $candidateId)
            ->max('sort_order') ?? -1;

        return CandidateEducation::create([
            'candidate_id' => $candidateId,
            'institution_name' => $data['institution_name'],
            'degree' => $data['degree'],
            'field_of_study' => $data['field_of_study'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'sort_order' => $maxSortOrder + 1,
        ]);
    }

    /**
     * Update an education entry, verifying it belongs to the candidate.
     *
     * @param  string  $candidateId
     * @param  string  $entryId
     * @param  array<string, mixed>  $data
     * @return CandidateEducation
     */
    public function updateEducation(string $candidateId, string $entryId, array $data): CandidateEducation
    {
        $entry = CandidateEducation::where('id', $entryId)
            ->where('candidate_id', $candidateId)
            ->firstOrFail();

        $allowedFields = ['institution_name', 'degree', 'field_of_study', 'start_date', 'end_date'];
        $updateData = array_intersect_key($data, array_flip($allowedFields));

        $entry->update($updateData);
        $entry->refresh();

        return $entry;
    }

    /**
     * Delete an education entry, verifying it belongs to the candidate.
     *
     * @param  string  $candidateId
     * @param  string  $entryId
     */
    public function deleteEducation(string $candidateId, string $entryId): void
    {
        $entry = CandidateEducation::where('id', $entryId)
            ->where('candidate_id', $candidateId)
            ->firstOrFail();

        $entry->delete();
    }

    /**
     * Reorder education entries by setting sort_order based on the provided ordered IDs.
     *
     * @param  string  $candidateId
     * @param  array<int, string>  $orderedIds
     */
    public function reorderEducation(string $candidateId, array $orderedIds): void
    {
        DB::transaction(function () use ($candidateId, $orderedIds) {
            foreach ($orderedIds as $index => $id) {
                CandidateEducation::where('id', $id)
                    ->where('candidate_id', $candidateId)
                    ->update(['sort_order' => $index]);
            }
        });
    }

    /**
     * Replace all skills for a candidate. Deletes existing skills and inserts the new list.
     *
     * @param  string  $candidateId
     * @param  array<int, array{name: string, category: string}>  $skills
     * @return array<int, array<string, mixed>>
     */
    public function replaceSkills(string $candidateId, array $skills): array
    {
        // Ensure the candidate exists
        Candidate::findOrFail($candidateId);

        return DB::transaction(function () use ($candidateId, $skills) {
            // Delete all existing skills for this candidate
            CandidateSkill::where('candidate_id', $candidateId)->delete();

            // Insert new skills with sort_order
            $created = [];
            foreach ($skills as $index => $skill) {
                $created[] = CandidateSkill::create([
                    'candidate_id' => $candidateId,
                    'name' => $skill['name'],
                    'category' => $skill['category'],
                    'sort_order' => $index,
                ]);
            }

            return array_map(fn (CandidateSkill $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'category' => $s->category,
                'sort_order' => $s->sort_order,
            ], $created);
        });
    }
}
