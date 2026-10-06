<?php

namespace Database\Factories;

use App\Enums\SmtpEncryption;
use App\Models\SmtpAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SmtpAccount> */
class SmtpAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'Primary SMTP',
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => 587,
            'smtp_username' => fake()->userName(),
            'smtp_password_encrypted' => 'secret-password',   // encrypted by the model cast
            'encryption' => SmtpEncryption::Tls,
            'from_name' => fake()->company(),
            'from_email' => fake()->safeEmail(),
            'status' => 'active',
        ];
    }
}
