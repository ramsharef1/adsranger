<?php

namespace Database\Factories;

use App\Models\ReachOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ReachOffer> */
class ReachOfferFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $payout = fake()->randomFloat(3, 1, 40);

        return [
            'title' => fake()->words(3, true),
            'network' => 'arabclicks',
            'external_id' => (string) fake()->unique()->numberBetween(1000, 999999),
            'payout_amount' => $payout,
            'publisher_payout_amount' => round($payout * 0.7, 3),
            'currency' => 'USD',
            'offer_type' => 'cps',
            'allow_incent' => false,
            'landing_url' => 'https://'.fake()->domainName().'/',
            'status' => 'active',
        ];
    }
}
