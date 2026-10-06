<?php

namespace Database\Factories;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Campaign> */
class CampaignFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->sentence(3),
            'subject' => 'Hello {{name}}',
            'body_html' => '<p>Hello {{name}},</p><p>We visited {{website}}.</p>',
            'body_text' => "Hello {{name}},\n\nWe visited {{website}}.",
            'excel_filename' => 'companies.xlsx',
            'excel_headers' => ['Name', 'Website', 'Email', 'Contact', 'Status'],
            'column_mapping' => [
                'name' => 'Name', 'email' => 'Email', 'website' => 'Website',
                'contact' => 'Contact', 'status' => 'Status',
            ],
            'batch_size' => 50,
            'status' => CampaignStatus::Draft,
        ];
    }
}
