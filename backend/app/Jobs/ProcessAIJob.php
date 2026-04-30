<?php

namespace App\Jobs;

use App\Contracts\OpenAIServiceInterface;
use App\Models\AIJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queue job that processes AI content generation requests.
 *
 * Updates the AI job status through its lifecycle:
 * pending → processing → completed (success) or failed (after retries).
 *
 * Retries up to 2 additional times with exponential backoff (2s, 8s).
 * Max execution timeout: 30 seconds.
 */
class ProcessAIJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     * 1 initial + 2 retries = 3 total.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 30;

    /**
     * Calculate the number of seconds to wait before retrying.
     * Exponential backoff: 2s, 8s.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [2, 8];
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        public AIJob $aiJob,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(OpenAIServiceInterface $openAIService): void
    {
        $startTime = microtime(true);

        // Update status to processing
        $this->aiJob->update(['status' => 'processing']);

        try {
            $result = $this->callOpenAIService($openAIService);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $this->aiJob->update([
                'status' => 'completed',
                'result_data' => $result['data'],
                'tokens_used' => $result['tokens_used'],
                'processing_duration_ms' => $durationMs,
            ]);
        } catch (\Throwable $e) {
            Log::error('AI job processing failed', [
                'job_id' => $this->aiJob->id,
                'job_type' => $this->aiJob->job_type,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            // If this was the last attempt, mark as failed
            if ($this->attempts() >= $this->tries) {
                $durationMs = (int) ((microtime(true) - $startTime) * 1000);

                $this->aiJob->update([
                    'status' => 'failed',
                    'error_message' => 'AI service temporarily unavailable. Please try again.',
                    'processing_duration_ms' => $durationMs,
                ]);

                // Don't rethrow — we've handled the final failure
                return;
            }

            // Rethrow to trigger retry
            throw $e;
        }
    }

    /**
     * Handle a job failure after all retries are exhausted.
     */
    public function failed(?\Throwable $exception): void
    {
        $this->aiJob->update([
            'status' => 'failed',
            'error_message' => 'AI service temporarily unavailable. Please try again.',
        ]);

        Log::error('AI job permanently failed', [
            'job_id' => $this->aiJob->id,
            'job_type' => $this->aiJob->job_type,
            'error' => $exception?->getMessage(),
        ]);
    }

    /**
     * Call the appropriate OpenAI service method based on job type.
     *
     * @return array{data: array<string, mixed>, tokens_used: int}
     */
    protected function callOpenAIService(OpenAIServiceInterface $openAIService): array
    {
        $inputData = $this->aiJob->input_data;

        $result = match ($this->aiJob->job_type) {
            'summary' => $openAIService->generateSummary($inputData),
            'bullets' => $openAIService->generateBullets($inputData),
            'skills' => $openAIService->suggestSkills($inputData),
            'ats_optimize' => $openAIService->optimizeATS($inputData),
            'improve' => $openAIService->improveText($inputData),
            default => throw new \InvalidArgumentException("Unknown AI job type: {$this->aiJob->job_type}"),
        };

        $tokensUsed = $result['tokens_used'] ?? 0;
        unset($result['tokens_used']);

        return [
            'data' => $result,
            'tokens_used' => $tokensUsed,
        ];
    }
}
