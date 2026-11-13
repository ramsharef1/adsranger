<?php

namespace Database\Factories;

use App\Models\ReachPublisher;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ReachPublisher> */
class ReachPublisherFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'domain_or_app' => fake()->unique()->domainName(),
            'status' => 'pending_verification',
            'family_safe' => true,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn () => ['status' => 'verified']);
    }
}
