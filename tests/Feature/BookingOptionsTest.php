<?php

namespace Tests\Feature;

use App\Models\AirwayBill;
use App\Models\BookingOption;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vendor;
use Database\Seeders\BookingOptionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_add_update_disable_or_enable_options(): void
    {
        $this->get('/booking-options')->assertRedirect('/login');
        $this->get('/booking-options/search?type=origin')->assertRedirect('/login');
        $option = BookingOption::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->get('/booking-options')->assertForbidden();
        $this->post('/booking-options', ['type' => 'origin', 'name' => 'Mumbai'])->assertForbidden();
        $this->put('/booking-options/'.$option->id, ['name' => 'Changed', 'enabled' => false])->assertForbidden();
        $this->assertDatabaseHas('booking_options', ['id' => $option->id, 'name' => $option->name, 'enabled' => true]);
        $this->assertDatabaseCount('booking_options', 1);
    }

    public function test_admin_can_manage_all_three_option_types_without_changing_old_bills(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $bill = AirwayBill::factory()->create(['origin' => 'Delhi', 'destination' => 'London', 'service' => 'Express']);
        foreach (['origin' => 'Delhi', 'destination' => 'London', 'service' => 'Express'] as $type => $name) {
            $this->post('/booking-options', ['type' => $type, 'name' => $name])->assertRedirect();
            $option = BookingOption::where('type', $type)->firstOrFail();
            $this->put('/booking-options/'.$option->id, ['name' => $name.' Updated', 'enabled' => 0, 'type' => 'other'])->assertRedirect();
            $this->assertDatabaseHas('booking_options', ['id' => $option->id, 'type' => $type, 'name' => $name.' Updated', 'enabled' => false]);
            $this->put('/booking-options/'.$option->id, ['name' => $name.' Updated', 'enabled' => 1])->assertRedirect();
            $this->assertTrue($option->fresh()->enabled);
            $this->get('/booking-options?type='.$type)->assertOk()->assertSee($name.' Updated');
        }
        $this->assertSame('Delhi', $bill->fresh()->origin);
        $this->assertSame('London', $bill->fresh()->destination);
        $this->assertSame('Express', $bill->fresh()->service);
    }

    public function test_option_search_is_filtered_by_type_enabled_status_and_search_term_and_limited(): void
    {
        $this->actingAs(User::factory()->create());
        BookingOption::factory()->create(['type' => 'origin', 'name' => 'Delhi']);
        BookingOption::factory()->create(['type' => 'origin', 'name' => 'Delhi Disabled', 'enabled' => false]);
        BookingOption::factory()->create(['type' => 'destination', 'name' => 'Delhi Destination']);
        BookingOption::factory()->count(30)->create(['type' => 'origin']);
        $this->getJson('/booking-options/search?type=origin&q=Delhi')->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'Delhi');
        $this->getJson('/booking-options/search?type=origin')->assertOk()->assertJsonCount(20);
        $this->getJson('/booking-options/search?type=service&q=missing')->assertOk()->assertExactJson([]);
        $this->getJson('/booking-options/search?type=invalid')->assertUnprocessable()->assertJsonValidationErrors('type');
    }

    public function test_duplicate_and_invalid_options_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $option = BookingOption::factory()->create(['type' => 'origin', 'name' => 'Delhi']);
        $this->post('/booking-options', ['type' => 'origin', 'name' => ' Delhi '])->assertSessionHasErrors('name');
        $this->post('/booking-options', ['type' => 'wrong', 'name' => 'Other'])->assertSessionHasErrors('type');
        $this->post('/booking-options', ['type' => ['origin'], 'name' => 'Other'])->assertSessionHasErrors('type');
        $this->post('/booking-options', ['type' => 'service', 'name' => ''])->assertSessionHasErrors('name');
        $this->put('/booking-options/'.$option->id, ['name' => 'Delhi', 'enabled' => 'bad'])->assertSessionHasErrors('enabled');
        $this->assertDatabaseCount('booking_options', 1);
        $this->post('/booking-options', ['type' => 'destination', 'name' => 'Delhi'])->assertRedirect();
        $this->assertDatabaseCount('booking_options', 2);
    }

    public function test_all_three_booking_fields_reject_disabled_unlisted_or_wrong_type_options(): void
    {
        $this->actingAs(User::factory()->create());
        $payload = ['number' => 'TEST-MANAGED-1', 'vendor_id' => Vendor::factory()->create()->id, 'customer_id' => Customer::factory()->create()->id, 'bill_date' => now()->toDateString(), 'origin' => 'Delhi', 'destination' => 'London', 'service' => 'Express', 'consignee_name' => 'Recipient', 'consignee_phone' => '123456', 'consignee_postal_code' => 'HP13 6SZ', 'shipper_name' => 'Original Shipper', 'shipper_phone' => '+91 1234567890', 'shipper_postal_code' => '001234', 'shipper_address' => 'Original Shipper Address', 'consignee_address' => 'UK', 'contents' => 'Clothing', 'pieces' => 1, 'weight' => 2, 'volumetric_weight' => 1, 'chargeable_weight' => 2, 'declared_value' => 500, 'currency' => 'INR', 'freight' => 100, 'other_charges' => 0, 'tax_rate' => 18];
        foreach (['origin' => 'Delhi', 'destination' => 'London', 'service' => 'Express'] as $type => $name) {
            BookingOption::factory()->create(['type' => $type, 'name' => $name]);
        }
        foreach (['origin', 'destination', 'service'] as $type) {
            BookingOption::where('type', $type)->update(['enabled' => false]);
            $this->post('/bills', $payload)->assertSessionHasErrors($type);
            BookingOption::where('type', $type)->update(['enabled' => true]);
        }
        $this->post('/bills', array_replace($payload, ['origin' => 'London']))->assertSessionHasErrors('origin');
        $this->post('/bills', array_replace($payload, ['service' => 'Unlisted']))->assertSessionHasErrors('service');
        $this->assertDatabaseCount('airway_bills', 0);
        $this->post('/bills', $payload)->assertRedirect();
        $this->assertDatabaseHas('airway_bills', ['number' => 'TEST-MANAGED-1', 'origin' => 'Delhi', 'destination' => 'London', 'service' => 'Express']);
    }

    public function test_bill_form_has_three_searchable_dropdowns_and_retains_submitted_values(): void
    {
        $this->actingAs(User::factory()->create());
        $this->withSession(['_old_input' => ['origin' => 'Delhi', 'destination' => 'London', 'service' => 'Express']])->get('/bills/create')->assertOk()->assertSee('booking-origin-results')->assertSee('booking-destination-results')->assertSee('booking-service-results')->assertSee('booking-options.js')->assertSee('value="Delhi"', false);
    }

    public function test_admin_search_and_disabled_filter_work_and_names_are_escaped(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        BookingOption::factory()->create(['type' => 'service', 'name' => '<script>test()</script>', 'enabled' => false]);
        BookingOption::factory()->create(['type' => 'service', 'name' => 'Enabled Express']);
        $this->get('/booking-options?type=service&status=disabled&q=script')->assertOk()->assertSee('<script>test()</script>')->assertDontSee('<script>test()</script>', false)->assertDontSee('Enabled Express');
    }

    public function test_default_seeder_does_not_reenable_disabled_choices_or_duplicate_entries(): void
    {
        $this->seed(BookingOptionSeeder::class);
        BookingOption::where('type', 'origin')->where('name', 'Delhi')->update(['enabled' => false]);
        $this->seed(BookingOptionSeeder::class);
        $this->assertDatabaseCount('booking_options', 30);
        $this->assertDatabaseHas('booking_options', ['type' => 'origin', 'name' => 'Delhi', 'enabled' => false]);
    }

    public function test_backup_includes_managed_options(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        BookingOption::factory()->create(['type' => 'service', 'name' => 'Disabled Service', 'enabled' => false]);
        $response = $this->post('/backup')->assertOk();
        $restored = new \PDO('sqlite::memory:');
        $restored->exec($response->streamedContent());
        $this->assertSame('Disabled Service', $restored->query('SELECT name FROM booking_options')->fetchColumn());
        $this->assertSame(0, (int) $restored->query('SELECT enabled FROM booking_options')->fetchColumn());
    }
}
