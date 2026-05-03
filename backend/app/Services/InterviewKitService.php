<?php

namespace App\Services;

use App\Models\InterviewKit;
use App\Models\PipelineStage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class InterviewKitService
{
    /**
     * List all interview kits for a job posting, grouped by pipeline stage.
     * Returns kits with question counts.
     */
    public function listForJob(string $jobId, string $tenantId): array
    {
        $stages = PipelineStage::whereHas('jobPosting', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })
            ->where('job_posting_id', $jobId)
            ->orderBy('sort_order')
            ->get();

        if ($stages->isEmpty()) {
            throw new HttpResponseException(
                response()->json([
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'Job posting not found.',
                    ],
                ], 404)
            );
        }

        $stageIds = $stages->pluck('id');

        $kits = InterviewKit::whereIn('pipeline_stage_id', $stageIds)
            ->withCount('questions')
            ->orderBy('created_at')
            ->get()
            ->groupBy('pipeline_stage_id');

        return $stages->map(function ($stage) use ($kits) {
            $stageKits = $kits->get($stage->id, collect());

            return [
                'stage_id' => $stage->id,
                'stage_name' => $stage->name,
                'kits' => $stageKits->map(function ($kit) {
                    return [
                        'id' => $kit->id,
                        'name' => $kit->name,
                        'description' => $kit->description,
                        'question_count' => $kit->questions_count,
                        'created_at' => $kit->created_at->toIso8601String(),
                    ];
                })->values()->all(),
            ];
        })->all();
    }

    /**
     * Get a single interview kit with all questions.
     * Validates tenant ownership.
     */
    public function getDetail(string $kitId, string $tenantId): ?InterviewKit
    {
        return InterviewKit::whereHas('pipelineStage', function ($q) use ($tenantId) {
            $q->whereHas('jobPosting', function ($q2) use ($tenantId) {
                $q2->where('tenant_id', $tenantId);
            });
        })
            ->with('questions')
            ->find($kitId);
    }

    /**
     * Create a new interview kit with questions for a pipeline stage.
     * Validates that the stage belongs to the job and tenant.
     */
    public function create(array $data, string $stageId, string $jobId, string $tenantId): InterviewKit
    {
        // Validate stage belongs to job and tenant
        $stage = PipelineStage::where('id', $stageId)
            ->where('job_posting_id', $jobId)
            ->whereHas('jobPosting', function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
            })
            ->first();

        if (!$stage) {
            throw new HttpResponseException(
                response()->json([
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'Pipeline stage does not belong to the specified job posting.',
                    ],
                ], 422)
            );
        }

        return DB::transaction(function () use ($data, $stageId) {
            $kit = InterviewKit::create([
                'pipeline_stage_id' => $stageId,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            foreach ($data['questions'] as $question) {
                $kit->questions()->create([
                    'text' => $question['text'],
                    'category' => $question['category'],
                    'sort_order' => $question['sort_order'],
                    'scoring_rubric' => $question['scoring_rubric'] ?? null,
                ]);
            }

            $kit->load('questions');

            return $kit;
        });
    }

    /**
     * Update an interview kit's name, description, and replace questions.
     */
    public function update(InterviewKit $kit, array $data): InterviewKit
    {
        return DB::transaction(function () use ($kit, $data) {
            $kit->update(array_filter([
                'name' => $data['name'] ?? null,
                'description' => array_key_exists('description', $data) ? $data['description'] : null,
            ], fn ($v, $k) => array_key_exists($k, $data), ARRAY_FILTER_USE_BOTH));

            if (isset($data['questions'])) {
                // Replace all questions
                $kit->questions()->delete();

                foreach ($data['questions'] as $question) {
                    $kit->questions()->create([
                        'text' => $question['text'],
                        'category' => $question['category'],
                        'sort_order' => $question['sort_order'],
                        'scoring_rubric' => $question['scoring_rubric'] ?? null,
                    ]);
                }
            }

            $kit->load('questions');

            return $kit;
        });
    }

    /**
     * Delete an interview kit and its questions.
     */
    public function delete(InterviewKit $kit): void
    {
        $kit->delete();
    }

    /**
     * Get available default kit templates.
     */
    public function getDefaultTemplates(): array
    {
        $templates = config('interview_kit_templates', []);

        return collect($templates)->map(function ($template, $key) {
            return [
                'key' => $key,
                'name' => $template['name'],
                'description' => $template['description'],
                'questions' => collect($template['questions'])->map(function ($q) {
                    return [
                        'text' => $q['text'],
                        'category' => $q['category'],
                        'scoring_rubric' => $q['scoring_rubric'] ?? null,
                    ];
                })->all(),
            ];
        })->values()->all();
    }

    /**
     * Create a kit from a default template, copying it as an independent instance.
     */
    public function createFromTemplate(string $templateKey, string $stageId, string $jobId, string $tenantId): InterviewKit
    {
        $templates = config('interview_kit_templates', []);

        if (!isset($templates[$templateKey])) {
            throw new HttpResponseException(
                response()->json([
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'Template not found.',
                    ],
                ], 404)
            );
        }

        $template = $templates[$templateKey];

        $questions = collect($template['questions'])->map(function ($q, $index) {
            return [
                'text' => $q['text'],
                'category' => $q['category'],
                'sort_order' => $index,
                'scoring_rubric' => $q['scoring_rubric'] ?? null,
            ];
        })->all();

        return $this->create([
            'name' => $template['name'],
            'description' => $template['description'],
            'questions' => $questions,
        ], $stageId, $jobId, $tenantId);
    }
}
