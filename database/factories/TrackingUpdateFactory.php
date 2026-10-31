<?php

namespace Database\Factories;

use App\Models\Shipment;
use App\Models\TrackingUpdate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TrackingUpdate> */
class TrackingUpdateFactory extends Factory
{
    public function definition(): array
    {
        return ['shipment_number' => Shipment::factory()->create()->number, 'created_by' => User::factory(), 'status' => 'Booked', 'location' => 'Delhi', 'occurred_at' => now()];
    }
}
