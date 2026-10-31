<?php

namespace Database\Factories;

use App\Models\EmailSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EmailSetting> */
class EmailSettingFactory extends Factory
{
    public function definition(): array
    {
        return ['enabled' => true, 'host' => 'smtp.example.test', 'port' => 587, 'encryption' => 'starttls', 'username' => 'sender@example.test', 'password' => 'test-smtp-secret', 'from_address' => 'sender@example.test', 'from_name' => 'Logistics', 'application_url' => 'https://example.test/propeller'];
    }
}
