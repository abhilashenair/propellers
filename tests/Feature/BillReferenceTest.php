<?php

namespace Tests\Feature;

use App\Models\AirwayBill;
use App\Models\BookingOption;
use App\Models\Customer;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Uid\Ulid;
use Tests\TestCase;

class BillReferenceTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        foreach (['origin' => 'Delhi', 'destination' => 'London', 'service' => 'Express'] as $type => $name) {
            BookingOption::factory()->create(['type' => $type, 'name' => $name]);
        }

        return ['number' => 'AUTO-AWB-001', 'customer_id' => Customer::factory()->create()->id, 'vendor_id' => Vendor::factory()->create()->id, 'bill_date' => now()->toDateString(), 'origin' => 'Delhi', 'destination' => 'London', 'service' => 'Express', 'shipper_name' => 'Private Shipper', 'shipper_phone' => 'PRIVATE-PHONE', 'shipper_address' => 'PRIVATE-ADDRESS', 'shipper_postal_code' => '001234', 'consignee_name' => 'Private Recipient', 'consignee_phone' => 'PRIVATE-RECIPIENT-PHONE', 'consignee_address' => 'PRIVATE-RECIPIENT-ADDRESS', 'consignee_postal_code' => 'HP13 6SZ', 'contents' => 'Clothes', 'pieces' => 1, 'weight' => 2, 'volumetric_weight' => 1, 'chargeable_weight' => 2, 'declared_value' => 100, 'freight' => 100, 'other_charges' => 0, 'tax_rate' => 18, 'currency' => 'INR'];
    }

    public function test_creating_bill_generates_unique_reference_and_ignores_a_client_supplied_value(): void
    {
        $this->actingAs(User::factory()->create());
        $payload = $this->payload();
        $this->get('/bills/create')->assertOk()->assertSee('Generated automatically when saved')->assertDontSee('name="reference"', false);
        $this->post('/bills', $payload + ['reference' => 'FORGED-REFERENCE'])->assertRedirect();
        $first = AirwayBill::firstOrFail();
        $this->assertStringStartsWith('REF-', $first->reference);
        $this->assertTrue(Str::isUlid(substr($first->reference, 4)));
        $this->assertNotSame('FORGED-REFERENCE', $first->reference);
        $this->post('/bills', array_replace($payload, ['number' => 'AUTO-AWB-002']))->assertRedirect();
        $second = AirwayBill::latest('id')->firstOrFail();
        $this->assertNotSame($first->reference, $second->reference);
        $this->get('/bills/'.$first->id)->assertOk()->assertSee($first->reference);
        $this->get('/bills')->assertOk()->assertSee($first->reference);
    }

    public function test_customers_can_track_by_reference_or_airway_number_on_both_public_pages_and_embed(): void
    {
        $user = User::factory()->create();
        $shipment = Shipment::factory()->create(['number' => 'TRACK-AWB', 'created_by' => $user->id]);
        $bill = AirwayBill::factory()->create(['number' => $shipment->number, 'vendor_id' => $shipment->vendor_id, 'reference' => 'LEGACY-REF-001', 'consignee_name' => 'PRIVATE RECIPIENT', 'consignee_phone' => 'PRIVATE PHONE', 'consignee_address' => 'PRIVATE ADDRESS']);
        $shipment->updates()->create(['created_by' => $user->id, 'status' => 'Delivered', 'location' => 'London', 'message' => 'Delivery completed', 'occurred_at' => now()]);
        foreach (['/', '/track', '/embed/tracking'] as $url) {
            foreach ([$shipment->number, $bill->reference] as $identifier) {
                $this->get($url.'?'.http_build_query(['number' => $identifier]))->assertOk()->assertSee('TRACK-AWB')->assertSee('LEGACY-REF-001')->assertSee('Delivery completed')->assertDontSee('PRIVATE RECIPIENT')->assertDontSee('PRIVATE PHONE')->assertDontSee('PRIVATE ADDRESS');
            }
        }
        $this->get('/embed/tracking?number=LEGACY-REF')->assertOk()->assertSee('Shipment not found')->assertDontSee('Delivery completed');
    }

    public function test_reference_tracking_respects_vendor_filter_and_supports_existing_long_references(): void
    {
        $shipment = Shipment::factory()->create();
        $otherVendor = Vendor::factory()->create();
        $reference = str_repeat('R', 100);
        AirwayBill::factory()->create(['number' => $shipment->number, 'reference' => $reference]);
        $this->get('/track?'.http_build_query(['number' => $reference, 'vendor_id' => $shipment->vendor_id]))->assertOk()->assertSee($shipment->number);
        $this->get('/track?'.http_build_query(['number' => $reference, 'vendor_id' => $otherVendor->id]))->assertOk()->assertSee('Shipment not found');
        $this->get('/embed/tracking?'.http_build_query(['number' => $reference]))->assertOk()->assertSee($shipment->number);
    }

    public function test_staff_bill_search_and_archive_accept_reference_in_the_number_filter(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $shipment = Shipment::factory()->create();
        $bill = AirwayBill::factory()->create(['number' => $shipment->number]);
        foreach (['/bills', '/shipments', '/dashboard'] as $url) {
            $this->get($url.'?'.http_build_query(['number' => $bill->reference]))->assertOk()->assertSee($bill->number);
        }
        $shipment->updates()->create(['created_by' => $user->id, 'status' => 'Delivered', 'location' => 'London', 'occurred_at' => now()]);
        $this->get('/archived-data?'.http_build_query(['number' => $bill->reference]))->assertOk()->assertSee($bill->number);
    }

    public function test_missing_legacy_references_are_backfilled_without_replacing_existing_values(): void
    {
        $existing = AirwayBill::factory()->create(['reference' => 'PRESERVE-REF']);
        $missing = AirwayBill::factory()->create();
        $blank = AirwayBill::factory()->create();
        $migration = require database_path('migrations/2026_11_01_094458_generate_and_index_bill_references.php');
        $migration->down();
        DB::table('airway_bills')->where('id', $missing->id)->update(['reference' => null]);
        DB::table('airway_bills')->where('id', $blank->id)->update(['reference' => '']);
        $migration->up();
        $this->assertSame('PRESERVE-REF', $existing->fresh()->reference);
        $this->assertStringStartsWith('REF-', $missing->fresh()->reference);
        $this->assertStringStartsWith('REF-', $blank->fresh()->reference);
        $this->assertNotSame($missing->fresh()->reference, $blank->fresh()->reference);
    }

    public function test_database_rejects_duplicate_references(): void
    {
        AirwayBill::factory()->create(['reference' => 'UNIQUE-REF']);
        $this->expectException(QueryException::class);
        AirwayBill::factory()->create(['reference' => 'UNIQUE-REF']);
    }

    public function test_reference_cannot_be_reused_as_an_airway_number(): void
    {
        $this->actingAs(User::factory()->create());
        $payload = $this->payload();
        $this->post('/bills', $payload)->assertRedirect();
        $reference = AirwayBill::firstOrFail()->reference;
        $this->post('/bills', array_replace($payload, ['number' => $reference]))->assertSessionHasErrors('number');
        $this->post('/shipments', ['number' => $reference, 'vendor_id' => $payload['vendor_id'], 'origin' => 'Delhi', 'destination' => 'London'])->assertSessionHasErrors('number');
        $this->assertDatabaseCount('airway_bills', 1);
    }

    public function test_generated_reference_skips_a_collision_with_an_existing_airway_number(): void
    {
        $first = '01HRDBNHHCKNW2AK4Z29SN82T9';
        $next = '01HRDBNHHCKNW2AK4Z29SN82TA';
        Shipment::factory()->create(['number' => 'REF-'.$first]);
        $sequence = [$first, $next];
        Str::createUlidsUsing(function () use (&$sequence): Ulid {
            return new Ulid(array_shift($sequence));
        });
        try {
            $bill = AirwayBill::factory()->create();
            $this->assertSame('REF-'.$next, $bill->reference);
        } finally {
            Str::createUlidsNormally();
        }
    }
}
