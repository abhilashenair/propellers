<?php

namespace Database\Factories;

use App\Models\Shipment;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Shipment> */
class ShipmentFactory extends Factory
{
    public function definition(): array
    {
        return ['number' => fake()->unique()->numerify('AWB########'), 'vendor_id' => Vendor::factory(), 'created_by' => User::factory(), 'origin' => 'Delhi', 'destination' => 'London'];
    }
}
