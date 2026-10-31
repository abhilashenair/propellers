<?php

namespace Tests\Feature;

use App\Models\AirwayBill;
use App\Models\Customer;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\LogisticsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogisticsDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeder_creates_ten_records_per_module_and_ten_movements_per_airway(): void
    {
        $this->seed(LogisticsDemoSeeder::class);

        $this->assertDatabaseCount('customers', 10);
        $this->assertDatabaseCount('vendors', 10);
        $this->assertDatabaseCount('airway_bills', 10);
        $this->assertDatabaseCount('shipments', 10);
        $this->assertDatabaseCount('users', 10);
        $this->assertDatabaseCount('tracking_updates', 100);
        $this->assertSame(0, User::where('role', 'admin')->count());
        foreach (Shipment::withCount('updates')->get() as $shipment) {
            $this->assertSame(10, $shipment->updates_count);
        }
        $bill = AirwayBill::where('number', 'DEMO-AWB-01')->firstOrFail();
        $this->assertSame(141600, $bill->total_minor);
        $this->assertSame('Demo Customer 01', $bill->shipper_details['name']);
        $this->assertDatabaseMissing('tracking_updates', ['status' => 'Invalid']);
    }

    public function test_reseeding_does_not_duplicate_data_or_overwrite_existing_records(): void
    {
        $customer = Customer::factory()->create(['name' => 'Existing Customer']);
        $admin = User::factory()->create(['role' => 'admin']);
        $passwordHash = $admin->password;
        $this->seed(LogisticsDemoSeeder::class);
        Customer::where('email', 'demo-customer-01@example.test')->update(['name' => 'Manually edited demo customer']);

        $this->seed(LogisticsDemoSeeder::class);

        $this->assertDatabaseCount('customers', 11);
        $this->assertDatabaseCount('vendors', 10);
        $this->assertDatabaseCount('airway_bills', 10);
        $this->assertDatabaseCount('shipments', 10);
        $this->assertDatabaseCount('users', 11);
        $this->assertDatabaseCount('tracking_updates', 100);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'Existing Customer']);
        $this->assertDatabaseHas('customers', ['email' => 'demo-customer-01@example.test', 'name' => 'Manually edited demo customer']);
        $this->assertSame($passwordHash, $admin->fresh()->password);
        $this->assertSame(10, AirwayBill::where('created_by', $admin->id)->count());
    }
}
