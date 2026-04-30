<?php

namespace App\Services;

use App\Models\Resume;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PDFExportService
{
    /**
     * Maximum time allowed for PDF generation in seconds.
     */
    public const TIMEOUT_SECONDS = 10;

    /**
     * Valid template slugs.
     */
    public const VALID_TEMPLATES = ['clean', 'modern', 'professional', 'creative'];

    /**
     * Export a resume as a PDF file.
     *
     * @param  Resume  $resume
     * @return array{download_url: string, filename: string}
     *
     * @throws \RuntimeException if PDF generation fails
     */
    public function export(Resume $resume): array
    {
        $startTime = microtime(true);

        try {
            // 1. Load resume content and template slug
            $content = $resume->content ?? [];
            $templateSlug = $resume->template_slug ?? 'clean';

            // Validate template slug, fall back to 'clean' if invalid
            if (! in_array($templateSlug, self::VALID_TEMPLATES, true)) {
                $templateSlug = 'clean';
            }

            $viewName = "resume-templates.{$templateSlug}";

            // 2. Render Blade template with resume data → HTML string
            $html = view($viewName, [
                'content' => $content,
                'template' => $templateSlug,
            ])->render();

            // Check timeout before PDF generation
            $this->checkTimeout($startTime);

            // 3. Pass HTML to DomPDF with US Letter page size (8.5" × 11")
            $pdf = Pdf::loadHTML($html)
                ->setPaper('letter', 'portrait')
                ->setOption('isRemoteEnabled', false)
                ->setOption('isHtml5ParserEnabled', true);

            // 4. Generate PDF binary
            $pdfContent = $pdf->output();

            // Check timeout after PDF generation
            $this->checkTimeout($startTime);

            // 5. Store PDF in local file storage (storage/app/resumes/)
            $filename = $this->generateFilename($resume);
            $storagePath = "resumes/{$filename}";

            Storage::disk('local')->put($storagePath, $pdfContent);

            // 6. Return download URL (simple file path for now)
            return [
                'download_url' => $storagePath,
                'filename' => $filename,
            ];
        } catch (\RuntimeException $e) {
            // Re-throw runtime exceptions (like timeout)
            throw $e;
        } catch (\Throwable $e) {
            // 8. Log failures with full context
            Log::error('PDF export failed', [
                'resume_id' => $resume->id,
                'candidate_id' => $resume->candidate_id,
                'template_slug' => $resume->template_slug,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new \RuntimeException(
                'PDF generation failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Generate a unique filename for the PDF.
     *
     * @param  Resume  $resume
     * @return string
     */
    protected function generateFilename(Resume $resume): string
    {
        $slug = Str::slug($resume->title ?: 'resume');
        $timestamp = now()->format('Ymd_His');
        $short = Str::substr($resume->id, 0, 8);

        return "{$slug}_{$short}_{$timestamp}.pdf";
    }

    /**
     * Check if the operation has exceeded the timeout.
     *
     * @param  float  $startTime
     *
     * @throws \RuntimeException if timeout exceeded
     */
    protected function checkTimeout(float $startTime): void
    {
        $elapsed = microtime(true) - $startTime;

        if ($elapsed > self::TIMEOUT_SECONDS) {
            throw new \RuntimeException(
                'PDF generation timed out after ' . round($elapsed, 2) . ' seconds.'
            );
        }
    }
}
