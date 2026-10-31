<?php

namespace Tests\Feature;

use App\Models\AirwayBill;
use App\Models\Customer;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AirwayNumbersTest extends TestCase
{
    use RefreshDatabase;

    public function test_airway_numbers_only_lists_created_bills_with_customer_details(): void
    {
        $staff = User::factory()->create();
        $shipment = Shipment::factory()->create(['number' => 'BILLED-AWB-001']);
        Shipment::factory()->create(['number' => 'UNBILLED-AWB-002']);
        $customer = Customer::factory()->create(['name' => 'Shipment Customer', 'phone' => '123456789', 'email' => 'shipment@example.test', 'address' => 'Customer Full Address']);
        $bill = AirwayBill::factory()->create(['number' => $shipment->number, 'customer_id' => $customer->id, 'vendor_id' => $shipment->vendor_id]);

        $this->actingAs($staff)->get('/shipments')->assertOk()
            ->assertSee('BILLED-AWB-001')->assertDontSee('UNBILLED-AWB-002')
            ->assertSee('Shipment Customer')->assertSee('123456789')->assertSee('shipment@example.test')->assertSee('Customer Full Address')
            ->assertSee('Add movement update')->assertSee(route('bills.show', $bill))
            ->assertDontSee('Add airway number');
    }

    public function test_airway_search_filters_created_bill_numbers(): void
    {
        $staff = User::factory()->create();
        $first = Shipment::factory()->create(['number' => 'AWB-SEARCH-ONE']);
        $second = Shipment::factory()->create(['number' => 'AWB-SEARCH-TWO']);
        AirwayBill::factory()->create(['number' => $first->number]);
        AirwayBill::factory()->create(['number' => $second->number]);

        $this->actingAs($staff)->get('/shipments?q=SEARCH-ONE')->assertOk()->assertSee('AWB-SEARCH-ONE')->assertDontSee('AWB-SEARCH-TWO');
    }

    public function test_billed_airway_details_allow_staff_to_publish_customer_visible_movements(): void
    {
        $staff = User::factory()->create();
        $shipment = Shipment::factory()->create();
        $customer = Customer::factory()->create(['name' => 'Private Customer', 'phone' => 'PRIVATE-PHONE', 'address' => 'PRIVATE-ADDRESS']);
        AirwayBill::factory()->create(['number' => $shipment->number, 'customer_id' => $customer->id]);

        $this->actingAs($staff)->get(route('shipments.show', $shipment))->assertOk()->assertSee('Private Customer')->assertSee('PRIVATE-PHONE')->assertSee('PRIVATE-ADDRESS')->assertSee('Add movement update');
        $this->post(route('updates.store', $shipment->number), ['status' => 'In transit', 'location' => 'Arrival hub', 'message' => 'Movement published by staff', 'occurred_at' => now()->subMinute()->format('Y-m-d H:i:s')])->assertRedirect();
        $this->assertDatabaseHas('tracking_updates', ['shipment_number' => $shipment->number, 'status' => 'In transit', 'created_by' => $staff->id]);
        $this->post('/logout');
        $this->get('/track?number='.$shipment->number)->assertOk()->assertSee('Movement published by staff')->assertDontSee('PRIVATE-PHONE')->assertDontSee('PRIVATE-ADDRESS')->assertDontSee('Private Customer');
    }
}
