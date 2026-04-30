<?php

namespace App\Contracts;

/**
 * Contract for OpenAI API integration.
 *
 * Each method accepts input data and returns structured result data.
 * Implementations may call the real OpenAI API or return mock data.
 */
interface OpenAIServiceInterface
{
    /**
     * Generate a professional summary.
     *
     * @param  array{job_title: string, years_experience: int, work_history?: array<int, string>}  $input
     * @return array{summary: string, tokens_used: int}
     */
    public function generateSummary(array $input): array;

    /**
     * Generate work experience bullet points.
     *
     * @param  array{job_title: string, company_name: string, description: string}  $input
     * @return array{bullets: list<string>, tokens_used: int}
     */
    public function generateBullets(array $input): array;

    /**
     * Suggest relevant skills for a job title.
     *
     * @param  array{job_title: string, industry?: string, existing_skills?: list<string>}  $input
     * @return array{skills: array{technical: list<string>, soft: list<string>}, tokens_used: int}
     */
    public function suggestSkills(array $input): array;

    /**
     * Optimize resume content for ATS compatibility.
     *
     * @param  array{job_description: string, resume_content: array<string, mixed>}  $input
     * @return array{missing_keywords: list<string>, present_keywords: list<string>, suggestions: array{required_skills: list<string>, preferred_skills: list<string>, industry_terminology: list<string>}, tokens_used: int}
     */
    public function optimizeATS(array $input): array;

    /**
     * Improve existing text for clarity and impact.
     *
     * @param  array{text: string}  $input
     * @return array{original_text: string, improved_text: string, tokens_used: int}
     */
    public function improveText(array $input): array;
}
