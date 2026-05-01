<?php

namespace Database\Factories;

use App\Models\JobPosting;
use App\Models\PipelineStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PipelineStage>
 */
class PipelineStageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<PipelineStage>
     */
    protected $model = PipelineStage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_posting_id' => JobPosting::factory(),
            'name' => fake()->randomElement(['Applied', 'Screening', 'Interview', 'Offer', 'Hired', 'Rejected']),
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
