<?php

namespace Database\Seeders;

use App\Models\AirwayBill;
use App\Models\Customer;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LogisticsDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            for ($index = 1; $index <= 10; $index++) {
                $suffix = str_pad((string) $index, 2, '0', STR_PAD_LEFT);
                $staff = User::firstOrCreate(['email' => "demo-staff-{$suffix}@example.test"], [
                    'name' => "Demo Staff {$suffix}",
                    'password' => Str::random(64),
                ]);
                $owner = User::where('role', 'admin')->orderBy('id')->first() ?? $staff;
                $customer = Customer::firstOrCreate(['email' => "demo-customer-{$suffix}@example.test"], [
                    'name' => "Demo Customer {$suffix}",
                    'phone' => "+00 000 100 {$suffix}",
                    'address' => "{$index} Sample Street, Demo City, 000000, India (test data)",
                    'tax_number' => "DEMO-TAX-{$suffix}", 'postal_code' => '110037',
                ]);
                $vendor = Vendor::firstOrCreate(['email' => "demo-vendor-{$suffix}@example.test"], [
                    'name' => "Demo Logistics {$suffix}",
                    'phone' => "+00 000 200 {$suffix}",
                    'address' => "{$index} Sample Logistics Park, Delhi, India (test data)",
                    'contact_person' => "Demo Contact {$suffix}",
                ]);
                $destination = ['London', 'Dubai', 'Singapore', 'Paris', 'New York'][($index - 1) % 5];
                $shipment = Shipment::firstOrCreate(['number' => "DEMO-AWB-{$suffix}"], [
                    'vendor_id' => $vendor->id,
                    'created_by' => $owner->id,
                    'origin' => 'Delhi',
                    'destination' => $destination,
                ]);
                $freight = 1000 + ($index * 100);
                AirwayBill::firstOrCreate(['number' => $shipment->number], [
                    'reference' => "DEMO-REF-{$suffix}",
                    'carrier_number' => "DEMO-CARRIER-{$suffix}",
                    'customer_id' => $customer->id,
                    'vendor_id' => $shipment->vendor_id,
                    'created_by' => $owner->id,
                    'shipper_details' => $customer->only(['name', 'phone', 'address', 'tax_number', 'postal_code']),
                    'bill_date' => now()->subDays(11 - $index)->toDateString(),
                    'origin' => $shipment->origin,
                    'destination' => $shipment->destination,
                    'consignee_name' => "Demo Recipient {$suffix}",
                    'consignee_postal_code' => 'DEMO-'.$suffix,
                    'consignee_phone' => "+00 000 300 {$suffix}",
                    'consignee_address' => "{$index} Sample Delivery Road, {$destination} (test data)",
                    'contents' => 'Sample clothing and books — demonstration only',
                    'pieces' => ($index % 3) + 1,
                    'weight' => 5 + $index,
                    'volumetric_weight' => 4 + $index,
                    'chargeable_weight' => 5 + $index,
                    'dimensions' => '40 × 30 × 20 cm',
                    'declared_value' => 2000 + ($index * 100),
                    'service' => 'Demo International Express',
                    'currency' => 'INR',
                    'freight' => $freight,
                    'other_charges' => 100,
                    'tax_rate' => 18,
                    'total_minor' => ($freight + 100) * 118,
                    'notes' => 'DEMO DATA — for testing only.',
                ]);
                $statuses = ['Booked', 'Picked up', 'In transit', 'In transit', 'Customs clearance', 'Customs clearance', 'In transit', 'In transit', 'Out for delivery', ['Delivered', 'Out for delivery', 'Exception', 'In transit', 'Customs clearance'][($index - 1) % 5]];
                foreach ($statuses as $event => $status) {
                    $shipment->updates()->firstOrCreate(['message' => 'Demo movement '.($event + 1).' of 10. Test shipment only.'], [
                        'created_by' => $owner->id,
                        'status' => $status,
                        'location' => $event < 4 ? 'Delhi hub' : $destination.' hub',
                        'occurred_at' => now()->startOfDay()->subDays(11 - $index)->addHours($event),
                    ]);
                }
            }
        });
    }
}
