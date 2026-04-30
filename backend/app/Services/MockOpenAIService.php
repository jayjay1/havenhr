<?php

namespace App\Services;

use App\Contracts\OpenAIServiceInterface;

/**
 * Mock implementation of OpenAIServiceInterface for development and testing.
 *
 * Returns realistic fake data after a configurable delay to simulate API latency.
 * Bind this in AppServiceProvider; swap for a real implementation when ready.
 */
class MockOpenAIService implements OpenAIServiceInterface
{
    /**
     * Delay in seconds to simulate API latency.
     */
    protected int $delaySeconds;

    public function __construct(int $delaySeconds = 1)
    {
        $this->delaySeconds = $delaySeconds;
    }

    /**
     * {@inheritdoc}
     */
    public function generateSummary(array $input): array
    {
        $this->simulateLatency();

        $jobTitle = $input['job_title'] ?? 'Professional';
        $years = $input['years_experience'] ?? 5;
        $workHistory = $input['work_history'] ?? [];

        $workContext = '';
        if (! empty($workHistory)) {
            $workContext = ' Drawing on a background that includes ' . implode(', ', array_slice($workHistory, 0, 3)) . '.';
        }

        return [
            'summary' => "Results-driven {$jobTitle} with {$years}+ years of experience delivering high-impact solutions. "
                . 'Proven track record of leading cross-functional teams and driving measurable business outcomes. '
                . 'Skilled in strategic planning, stakeholder management, and continuous process improvement. '
                . "Passionate about leveraging technology to solve complex challenges and create value.{$workContext}",
            'tokens_used' => 150,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function generateBullets(array $input): array
    {
        $this->simulateLatency();

        $jobTitle = $input['job_title'] ?? 'Professional';
        $company = $input['company_name'] ?? 'Company';

        return [
            'bullets' => [
                "Led a team of 8 engineers to deliver a critical platform migration at {$company}, reducing infrastructure costs by 35%.",
                "Designed and implemented a scalable microservices architecture as {$jobTitle}, improving system throughput by 200%.",
                'Established automated CI/CD pipelines that reduced deployment time from 4 hours to 15 minutes.',
                "Mentored 5 junior developers, resulting in 3 promotions within 12 months at {$company}.",
                'Collaborated with product and design teams to launch 3 major features, increasing user engagement by 45%.',
            ],
            'tokens_used' => 200,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function suggestSkills(array $input): array
    {
        $this->simulateLatency();

        $existingSkills = array_map('strtolower', $input['existing_skills'] ?? []);

        $allTechnical = [
            'Python', 'JavaScript', 'TypeScript', 'React', 'Node.js',
            'AWS', 'Docker', 'Kubernetes', 'PostgreSQL', 'Redis',
            'GraphQL', 'REST APIs', 'CI/CD', 'Git', 'Terraform',
        ];

        $allSoft = [
            'Leadership', 'Communication', 'Problem Solving', 'Team Collaboration',
            'Project Management', 'Critical Thinking', 'Adaptability', 'Time Management',
        ];

        // Filter out existing skills (case-insensitive)
        $technical = array_values(array_filter(
            $allTechnical,
            fn (string $skill) => ! in_array(strtolower($skill), $existingSkills, true),
        ));

        $soft = array_values(array_filter(
            $allSoft,
            fn (string $skill) => ! in_array(strtolower($skill), $existingSkills, true),
        ));

        return [
            'skills' => [
                'technical' => array_slice($technical, 0, 10),
                'soft' => array_slice($soft, 0, 5),
            ],
            'tokens_used' => 120,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function optimizeATS(array $input): array
    {
        $this->simulateLatency();

        return [
            'missing_keywords' => [
                'agile methodology', 'stakeholder management', 'data-driven',
                'cross-functional', 'KPI tracking',
            ],
            'present_keywords' => [
                'leadership', 'project management', 'communication',
            ],
            'suggestions' => [
                'required_skills' => ['agile methodology', 'stakeholder management'],
                'preferred_skills' => ['data-driven decision making', 'KPI tracking'],
                'industry_terminology' => ['cross-functional collaboration', 'continuous improvement'],
            ],
            'tokens_used' => 250,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function improveText(array $input): array
    {
        $this->simulateLatency();

        $originalText = $input['text'] ?? '';

        return [
            'original_text' => $originalText,
            'improved_text' => 'Accomplished professional with a demonstrated history of delivering impactful results. '
                . 'Leveraged strategic thinking and technical expertise to drive organizational growth and operational excellence.',
            'tokens_used' => 100,
        ];
    }

    /**
     * Simulate API latency.
     */
    protected function simulateLatency(): void
    {
        if ($this->delaySeconds > 0) {
            sleep($this->delaySeconds);
        }
    }
}
