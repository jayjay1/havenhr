<?php

namespace App\Services;

use App\Contracts\OpenAIServiceInterface;

/**
 * Real OpenAI API integration service.
 *
 * Uses the OpenAI PHP client (openai-php/client) to call GPT-4.
 * Requires OPENAI_API_KEY to be set in the environment.
 *
 * If the openai-php/client package is not installed, this class
 * will throw a RuntimeException on instantiation. Use MockOpenAIService
 * as the default binding in AppServiceProvider for development/testing.
 *
 * Configuration:
 *   - Model: gpt-4
 *   - Temperature: 0.7
 */
class OpenAIService implements OpenAIServiceInterface
{
    protected mixed $client;

    protected string $model = 'gpt-4';

    protected float $temperature = 0.7;

    public function __construct()
    {
        $apiKey = config('services.openai.api_key', env('OPENAI_API_KEY'));

        if (empty($apiKey)) {
            throw new \RuntimeException(
                'OPENAI_API_KEY is not configured. Set it in your .env file or use MockOpenAIService for development.'
            );
        }

        if (! class_exists(\OpenAI::class)) {
            throw new \RuntimeException(
                'The openai-php/client package is not installed. Run: composer require openai-php/client'
            );
        }

        $this->client = \OpenAI::client($apiKey);
    }

    /**
     * {@inheritdoc}
     */
    public function generateSummary(array $input): array
    {
        $jobTitle = $input['job_title'] ?? 'Professional';
        $years = $input['years_experience'] ?? 5;
        $workHistory = $input['work_history'] ?? [];

        $workContext = '';
        if (! empty($workHistory)) {
            $workContext = "\n\nRelevant work history:\n" . implode("\n", $workHistory);
        }

        $prompt = "Write a 3-5 sentence professional summary for a {$jobTitle} with {$years} years of experience. "
            . 'Use third person, active voice, and no placeholder text or generic filler phrases. '
            . "Focus on accomplishments, skills, and value proposition.{$workContext}";

        return $this->callApi($prompt, 'summary');
    }

    /**
     * {@inheritdoc}
     */
    public function generateBullets(array $input): array
    {
        $jobTitle = $input['job_title'] ?? 'Professional';
        $company = $input['company_name'] ?? 'Company';
        $description = $input['description'] ?? '';

        $prompt = "Generate 4-6 achievement-oriented bullet points for a {$jobTitle} at {$company}. "
            . 'Each bullet should follow the format: accomplished X by doing Y, resulting in Z. '
            . 'Begin each bullet with a strong action verb and include quantifiable results where possible. '
            . "\n\nRole description: {$description}";

        $response = $this->callApi($prompt, 'bullets');

        // Parse the response into an array of bullet strings
        $text = $response['raw_text'] ?? '';
        $bullets = $this->parseBullets($text);

        return [
            'bullets' => $bullets,
            'tokens_used' => $response['tokens_used'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function suggestSkills(array $input): array
    {
        $jobTitle = $input['job_title'] ?? 'Professional';
        $industry = $input['industry'] ?? 'general';
        $existingSkills = $input['existing_skills'] ?? [];

        $excludeClause = '';
        if (! empty($existingSkills)) {
            $excludeClause = "\n\nExclude these skills the candidate already has: " . implode(', ', $existingSkills);
        }

        $prompt = "Suggest 10-15 relevant skills for a {$jobTitle} in the {$industry} industry, "
            . 'categorized as technical skills and soft skills. '
            . "Return the skills in two groups: 'technical' and 'soft'.{$excludeClause}";

        $response = $this->callApi($prompt, 'skills');

        // Parse into structured format
        $text = $response['raw_text'] ?? '';
        $skills = $this->parseSkills($text, $existingSkills);

        return [
            'skills' => $skills,
            'tokens_used' => $response['tokens_used'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function optimizeATS(array $input): array
    {
        $jobDescription = $input['job_description'] ?? '';
        $resumeContent = $input['resume_content'] ?? [];

        $resumeText = is_array($resumeContent) ? json_encode($resumeContent) : (string) $resumeContent;

        $prompt = "Analyze this job description and compare against the resume content. "
            . "List missing keywords that should be added, keywords already present, and categorize suggestions "
            . "into required skills, preferred skills, and industry terminology.\n\n"
            . "Job Description:\n{$jobDescription}\n\n"
            . "Resume Content:\n{$resumeText}";

        $response = $this->callApi($prompt, 'ats_optimize');

        // Parse into structured format
        return [
            'missing_keywords' => $this->parseKeywordList($response['raw_text'] ?? '', 'missing'),
            'present_keywords' => $this->parseKeywordList($response['raw_text'] ?? '', 'present'),
            'suggestions' => [
                'required_skills' => [],
                'preferred_skills' => [],
                'industry_terminology' => [],
            ],
            'tokens_used' => $response['tokens_used'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function improveText(array $input): array
    {
        $originalText = $input['text'] ?? '';

        $prompt = "Rewrite this text to be more concise, impactful, and professional while preserving "
            . "the original meaning and factual content. Check for grammar errors and inconsistent tone. "
            . "Return only the improved text.\n\nOriginal text:\n{$originalText}";

        $response = $this->callApi($prompt, 'improve');

        return [
            'original_text' => $originalText,
            'improved_text' => $response['raw_text'] ?? $originalText,
            'tokens_used' => $response['tokens_used'],
        ];
    }

    /**
     * Call the OpenAI API with the given prompt.
     *
     * @return array{raw_text: string, tokens_used: int}
     */
    protected function callApi(string $prompt, string $context): array
    {
        $response = $this->client->chat()->create([
            'model' => $this->model,
            'temperature' => $this->temperature,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a professional resume writing assistant. Provide clear, concise, and impactful content.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ]);

        $text = $response->choices[0]->message->content ?? '';
        $tokensUsed = $response->usage->totalTokens ?? 0;

        return [
            'raw_text' => $text,
            'tokens_used' => $tokensUsed,
        ];
    }

    /**
     * Parse bullet points from raw text.
     *
     * @return list<string>
     */
    protected function parseBullets(string $text): array
    {
        $lines = explode("\n", $text);
        $bullets = [];

        foreach ($lines as $line) {
            $line = trim($line);
            // Remove common bullet prefixes
            $line = preg_replace('/^[\-\*\•\d+\.]\s*/', '', $line);
            $line = trim($line);

            if (! empty($line)) {
                $bullets[] = $line;
            }
        }

        return array_slice($bullets, 0, 6);
    }

    /**
     * Parse skills from raw text into categorized format.
     *
     * @param  list<string>  $existingSkills
     * @return array{technical: list<string>, soft: list<string>}
     */
    protected function parseSkills(string $text, array $existingSkills): array
    {
        $existingLower = array_map('strtolower', $existingSkills);
        $technical = [];
        $soft = [];

        $lines = explode("\n", $text);
        $currentCategory = 'technical';

        foreach ($lines as $line) {
            $line = trim($line);
            $lineLower = strtolower($line);

            if (str_contains($lineLower, 'soft')) {
                $currentCategory = 'soft';

                continue;
            }

            if (str_contains($lineLower, 'technical')) {
                $currentCategory = 'technical';

                continue;
            }

            $skill = preg_replace('/^[\-\*\•\d+\.]\s*/', '', $line);
            $skill = trim($skill);

            if (! empty($skill) && ! in_array(strtolower($skill), $existingLower, true)) {
                if ($currentCategory === 'soft') {
                    $soft[] = $skill;
                } else {
                    $technical[] = $skill;
                }
            }
        }

        return [
            'technical' => array_slice($technical, 0, 10),
            'soft' => array_slice($soft, 0, 5),
        ];
    }

    /**
     * Parse keyword lists from raw text.
     *
     * @return list<string>
     */
    protected function parseKeywordList(string $text, string $type): array
    {
        // Simple extraction — in production, use structured output or JSON mode
        $lines = explode("\n", $text);
        $keywords = [];

        foreach ($lines as $line) {
            $line = trim($line);
            $keyword = preg_replace('/^[\-\*\•\d+\.]\s*/', '', $line);
            $keyword = trim($keyword);

            if (! empty($keyword) && strlen($keyword) < 100) {
                $keywords[] = $keyword;
            }
        }

        return array_slice($keywords, 0, 15);
    }
}
