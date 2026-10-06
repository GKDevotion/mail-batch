<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CampaignRecipient> */
class CampaignRecipientFactory extends Factory
{
    public function definition(): array
    {
        $email = fake()->unique()->safeEmail();

        return [
            'campaign_id' => Campaign::factory(),
            'row_number' => fake()->unique()->numberBetween(2, 100000),
            'name' => fake()->company(),
            'email' => $email,
            'website' => 'https://'.fake()->domainName(),
            'contact' => fake()->phoneNumber(),
            'status' => 0,
            'is_valid_email' => true,
            'dedupe_key' => strtolower($email),
            'metadata' => [],
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => ['status' => 1, 'sent_at' => now()]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['error_message' => 'SMTP connection timed out', 'retry_count' => 1]);
    }

    public function skipped(string $reason = 'already_marked_sent'): static
    {
        return $this->state(fn () => ['status' => 1, 'skip_reason' => $reason]);
    }
}
