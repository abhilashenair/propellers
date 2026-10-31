<?php

namespace Database\Factories;

use App\Models\BookingOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BookingOption> */
class BookingOptionFactory extends Factory
{
    public function definition(): array
    {
        return ['type' => 'origin', 'name' => fake()->unique()->city(), 'enabled' => true];
    }
}
