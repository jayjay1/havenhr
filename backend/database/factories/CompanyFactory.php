<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Company>
     */
    protected $model = Company::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'email_domain' => fake()->unique()->domainName(),
            'subscription_status' => 'trial',
            'settings' => null,
        ];
    }

    /**
     * Indicate that the company has an active subscription.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_status' => 'active',
        ]);
    }

    /**
     * Indicate that the company has a suspended subscription.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_status' => 'suspended',
        ]);
    }
}
