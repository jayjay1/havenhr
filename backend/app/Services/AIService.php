<?php

namespace App\Services;

use App\Jobs\ProcessAIJob;
use App\Models\AIJob;
use Carbon\Carbon;

/**
 * Service for managing AI content generation requests.
 *
 * Handles job creation with rate limiting, job status retrieval,
 * and dispatching async processing via the queue.
 */
class AIService
{
    /**
     * Maximum AI requests per hour per candidate.
     */
    public const HOURLY_LIMIT = 20;

    /**
     * Maximum AI requests per day per candidate.
     */
    public const DAILY_LIMIT = 100;

    /**
     * Maximum input character length per request.
     */
    public const MAX_INPUT_LENGTH = 5000;

    /**
     * Valid AI job types.
     */
    public const VALID_JOB_TYPES = ['summary', 'bullets', 'skills', 'ats_optimize', 'improve'];

    /**
     * Create a new AI job, checking rate limits and input constraints.
     *
     * @param  string  $candidateId
     * @param  string  $jobType
     * @param  array<string, mixed>  $inputData
     * @return array{job_id: string, status: string}
     *
     * @throws \App\Exceptions\RateLimitExceededException
     * @throws \InvalidArgumentException
     */
    public function createJob(string $candidateId, string $jobType, array $inputData): array
    {
        // Validate job type
        if (! in_array($jobType, self::VALID_JOB_TYPES, true)) {
            throw new \InvalidArgumentException("Invalid AI job type: {$jobType}");
        }

        // Enforce max input length (check total serialized size)
        $inputJson = json_encode($inputData);
        if ($inputJson !== false && mb_strlen($inputJson) > self::MAX_INPUT_LENGTH + 200) {
            // Allow some overhead for JSON structure/keys beyond the 5000 char field limits
            throw new \InvalidArgumentException('Input data exceeds maximum length of ' . self::MAX_INPUT_LENGTH . ' characters.');
        }

        // Check rate limits
        $rateLimitResult = $this->checkRateLimit($candidateId);
        if ($rateLimitResult !== null) {
            throw new RateLimitExceededException(
                $rateLimitResult['message'],
                $rateLimitResult['retry_after'],
                $rateLimitResult['limit_type'],
                $rateLimitResult['limit'],
                $rateLimitResult['used'],
            );
        }

        // Create AI job record
        $aiJob = AIJob::create([
            'candidate_id' => $candidateId,
            'job_type' => $jobType,
            'input_data' => $inputData,
            'status' => 'pending',
        ]);

        // Dispatch to queue
        ProcessAIJob::dispatch($aiJob);

        return [
            'job_id' => $aiJob->id,
            'status' => 'pending',
        ];
    }

    /**
     * Get an AI job by ID, scoped to the candidate.
     *
     * @param  string  $candidateId
     * @param  string  $jobId
     * @return array<string, mixed>
     */
    public function getJob(string $candidateId, string $jobId): array
    {
        $job = AIJob::where('id', $jobId)
            ->where('candidate_id', $candidateId)
            ->firstOrFail();

        $result = [
            'id' => $job->id,
            'job_type' => $job->job_type,
            'status' => $job->status,
            'created_at' => $job->created_at->toIso8601String(),
        ];

        if ($job->status === 'completed' && $job->result_data !== null) {
            $result['result'] = $job->result_data;
        }

        if ($job->status === 'failed' && $job->error_message !== null) {
            $result['error_message'] = $job->error_message;
        }

        return $result;
    }

    /**
     * Check rate limits for a candidate.
     *
     * Returns null if within limits, or an array with retry info if exceeded.
     *
     * @param  string  $candidateId
     * @return array{message: string, retry_after: int, limit_type: string, limit: int, used: int}|null
     */
    public function checkRateLimit(string $candidateId): ?array
    {
        $now = Carbon::now();

        // Check hourly limit
        $hourlyCount = AIJob::where('candidate_id', $candidateId)
            ->where('created_at', '>=', $now->copy()->subHour())
            ->count();

        if ($hourlyCount >= self::HOURLY_LIMIT) {
            // Find the oldest job in the window to calculate retry_after
            $oldestInWindow = AIJob::where('candidate_id', $candidateId)
                ->where('created_at', '>=', $now->copy()->subHour())
                ->orderBy('created_at', 'asc')
                ->first();

            $retryAfter = $oldestInWindow
                ? (int) $now->copy()->subHour()->diffInSeconds($oldestInWindow->created_at, false) + 1
                : 3600;

            // Ensure retry_after is at least 1 second
            $retryAfter = max(1, $retryAfter);

            return [
                'message' => 'You have exceeded the AI usage limit. Please try again later.',
                'retry_after' => $retryAfter,
                'limit_type' => 'hourly',
                'limit' => self::HOURLY_LIMIT,
                'used' => $hourlyCount,
            ];
        }

        // Check daily limit
        $dailyCount = AIJob::where('candidate_id', $candidateId)
            ->where('created_at', '>=', $now->copy()->subDay())
            ->count();

        if ($dailyCount >= self::DAILY_LIMIT) {
            $oldestInWindow = AIJob::where('candidate_id', $candidateId)
                ->where('created_at', '>=', $now->copy()->subDay())
                ->orderBy('created_at', 'asc')
                ->first();

            $retryAfter = $oldestInWindow
                ? (int) $now->copy()->subDay()->diffInSeconds($oldestInWindow->created_at, false) + 1
                : 86400;

            $retryAfter = max(1, $retryAfter);

            return [
                'message' => 'You have exceeded the daily AI usage limit. Please try again later.',
                'retry_after' => $retryAfter,
                'limit_type' => 'daily',
                'limit' => self::DAILY_LIMIT,
                'used' => $dailyCount,
            ];
        }

        return null;
    }
}
