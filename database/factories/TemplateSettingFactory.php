<?php

namespace Database\Factories;

use App\Models\TemplateSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TemplateSetting> */
class TemplateSettingFactory extends Factory
{
    public function definition(): array
    {
        return ['terms_and_conditions' => fake()->paragraph()];
    }
}
