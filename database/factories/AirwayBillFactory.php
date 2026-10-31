<?php

namespace Database\Factories;

use App\Models\AirwayBill;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AirwayBill> */
class AirwayBillFactory extends Factory
{
    public function definition(): array
    {
        return ['number' => fake()->unique()->numerify('AWB########'), 'vendor_id' => Vendor::factory(), 'customer_id' => Customer::factory(), 'created_by' => User::factory(), 'bill_date' => now()->toDateString(), 'origin' => 'Delhi', 'destination' => 'London', 'consignee_name' => fake()->name(), 'consignee_phone' => fake()->phoneNumber(), 'consignee_address' => fake()->address(), 'contents' => 'Clothes', 'pieces' => 1, 'weight' => 2, 'chargeable_weight' => 2, 'service' => 'Express', 'currency' => 'INR', 'freight' => 100, 'other_charges' => 0, 'tax_rate' => 18, 'total_minor' => 11800];
    }
}
