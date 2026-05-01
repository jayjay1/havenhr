<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\JobPosting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JobPosting>
 */
class JobPostingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<JobPosting>
     */
    protected $model = JobPosting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->jobTitle();
        $slug = Str::slug($title) . '-' . substr((string) Str::uuid(), 0, 8);

        return [
            'tenant_id' => Company::factory(),
            'created_by' => User::factory(),
            'title' => $title,
            'slug' => $slug,
            'description' => fake()->paragraphs(3, true),
            'location' => fake()->city() . ', ' . fake()->stateAbbr(),
            'employment_type' => fake()->randomElement(['full-time', 'part-time', 'contract', 'internship']),
            'department' => fake()->randomElement(['Engineering', 'Marketing', 'Sales', 'HR', 'Finance', null]),
            'salary_min' => fake()->optional()->numberBetween(30000, 80000),
            'salary_max' => fake()->optional()->numberBetween(80000, 200000),
            'salary_currency' => 'USD',
            'requirements' => fake()->optional()->paragraph(),
            'benefits' => fake()->optional()->paragraph(),
            'remote_status' => fake()->optional()->randomElement(['remote', 'on-site', 'hybrid']),
            'status' => 'draft',
        ];
    }

    /**
     * Indicate that the job posting is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    /**
     * Indicate that the job posting is closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'closed',
            'published_at' => now()->subDays(30),
            'closed_at' => now(),
        ]);
    }

    /**
     * Indicate that the job posting is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'archived',
            'published_at' => now()->subDays(60),
            'closed_at' => now()->subDays(30),
        ]);
    }
}
