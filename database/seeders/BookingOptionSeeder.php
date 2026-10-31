<?php

namespace Database\Seeders;

use App\Models\BookingOption;
use Illuminate\Database\Seeder;

class BookingOptionSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'origin' => ['Delhi', 'Mumbai', 'Chennai', 'Bengaluru', 'Kolkata', 'Hyderabad', 'Kochi', 'Ahmedabad', 'Pune', 'Jaipur'],
            'destination' => ['London', 'Dubai', 'Singapore', 'Paris', 'New York', 'Muscat', 'Doha', 'Toronto', 'Sydney', 'Frankfurt'],
            'service' => ['Express', 'UK Express', 'Economy', 'International Express', 'Domestic Express', 'Air Freight', 'Road Freight', 'Door to Door', 'Document Express', 'Demo International Express'],
        ];
        foreach ($defaults as $type => $names) {
            foreach ($names as $name) {
                BookingOption::firstOrCreate(['type' => $type, 'name' => $name], ['enabled' => true]);
            }
        }
    }
}
