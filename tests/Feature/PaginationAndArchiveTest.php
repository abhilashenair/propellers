<?php

namespace Tests\Feature;

use App\Models\AirwayBill;
use App\Models\BookingOption;
use App\Models\Customer;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaginationAndArchiveTest extends TestCase
{
    use RefreshDatabase;

    private function consignment(User $owner, string $number, array $attributes = []): array
    {
        $vendor = $attributes['vendor_id'] ?? Vendor::factory()->create()->id;
        $customer = $attributes['customer_id'] ?? Customer::factory()->create()->id;
        $shipment = Shipment::factory()->create(['number' => $number, 'vendor_id' => $vendor, 'created_by' => $owner->id]);
        $bill = AirwayBill::factory()->create(array_replace(['number' => $number, 'vendor_id' => $vendor, 'customer_id' => $customer, 'created_by' => $owner->id, 'bill_date' => '2026-10-15'], $attributes));

        return [$shipment, $bill];
    }

    private function delivered(Shipment $shipment, string $date = '2026-10-17 12:00:00'): void
    {
        $shipment->updates()->create(['created_by' => $shipment->created_by, 'status' => 'Delivered', 'location' => 'Destination hub', 'message' => 'Delivery confirmed', 'occurred_at' => $date]);
    }

    public function test_all_workspace_record_lists_default_to_ten_and_accept_each_supported_page_size(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $customers = Customer::factory()->count(26)->create();
        $vendors = Vendor::factory()->count(26)->create();
        User::factory()->count(26)->create(['role' => 'staff']);
        BookingOption::factory()->count(26)->create();
        for ($i = 0; $i < 26; $i++) {
            [$shipment] = $this->consignment($admin, 'ACTIVE-'.$i, ['customer_id' => $customers[$i]->id, 'vendor_id' => $vendors[$i]->id]);
            [$archive] = $this->consignment($admin, 'ARCHIVE-'.$i, ['customer_id' => $customers[$i]->id, 'vendor_id' => $vendors[$i]->id]);
            $this->delivered($archive);
        }
        foreach (['/customers' => 'records', '/vendors' => 'records', '/staff' => 'staff', '/booking-options' => 'options', '/bills' => 'bills', '/shipments' => 'shipments', '/dashboard' => 'bills', '/archived-data' => 'bills'] as $url => $variable) {
            $response = $this->get($url)->assertOk()->assertSee('Rows per page');
            $page = $response->viewData($variable);
            $this->assertSame(10, $page->perPage(), $url);
            $this->assertCount(10, $page);
            foreach ([25, 50, 100] as $size) {
                $result = $this->get($url.'?per_page='.$size)->assertOk()->viewData($variable);
                $this->assertSame($size, $result->perPage(), $url);
                $this->assertCount(min(26, $size), $result);
            }
        }
    }

    public function test_marking_delivered_archives_immediately_without_losing_bill_or_public_tracking(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        [$shipment,$bill] = $this->consignment($user, 'DELIVERY-AWB');
        $this->get('/bills')->assertOk()->assertSee('DELIVERY-AWB');
        $this->post('/shipments/DELIVERY-AWB/updates', ['status' => 'Delivered', 'location' => 'London', 'occurred_at' => now()->format('Y-m-d H:i:s'), 'message' => 'Received successfully'])->assertRedirect()->assertSessionHas('success', 'Delivered. The consignment is now in Archived data.');
        $this->get('/archived-data')->assertOk()->assertSee('DELIVERY-AWB')->assertSee('Delivered');
        $this->get('/bills')->assertOk()->assertDontSee('DELIVERY-AWB');
        $this->get('/shipments')->assertOk()->assertDontSee('DELIVERY-AWB');
        $this->get('/dashboard')->assertOk()->assertDontSee('DELIVERY-AWB');
        $this->get('/bills/'.$bill->id)->assertOk()->assertSee('AIRWAY BILL COPY')->assertSee('Delivered — archived');
        $this->assertDatabaseHas('airway_bills', ['id' => $bill->id]);
        $this->post('/logout');
        $this->get('/track?number=DELIVERY-AWB')->assertOk()->assertSee('Received successfully');
        $this->get('/archived-data')->assertRedirect('/login');
    }

    public function test_archive_follows_event_chronology_and_breaks_equal_date_ties_by_newest_id(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        [$shipment,$bill] = $this->consignment($user, 'CHRONOLOGY');
        $this->delivered($shipment);
        $shipment->updates()->create(['created_by' => $user->id, 'status' => 'In transit', 'location' => 'Origin', 'occurred_at' => '2026-10-16 12:00:00']);
        $this->get('/archived-data')->assertOk()->assertSee('CHRONOLOGY');
        $this->get('/bills')->assertOk()->assertDontSee('CHRONOLOGY');
        $shipment->updates()->create(['created_by' => $user->id, 'status' => 'Exception', 'location' => 'Correction', 'occurred_at' => '2026-10-17 12:00:00']);
        $this->get('/bills')->assertOk()->assertSee('CHRONOLOGY')->assertSee('Exception');
        $this->get('/archived-data')->assertOk()->assertDontSee('CHRONOLOGY');
    }

    public function test_archive_supports_every_requested_filter_and_combined_filters_without_leaking_active_bills(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = Customer::factory()->create(['name' => 'Target Customer']);
        $vendor = Vendor::factory()->create(['name' => 'Target Vendor']);
        [$target,$bill] = $this->consignment($user, 'MATCH-AWB', ['customer_id' => $customer->id, 'vendor_id' => $vendor->id, 'origin' => 'Kochi', 'destination' => 'Muscat', 'service' => 'Air Express']);
        $this->delivered($target);
        [$other,$otherBill] = $this->consignment($user, 'OTHER-AWB', ['bill_date' => '2026-11-01']);
        $this->delivered($other, '2026-11-02 12:00:00');
        $this->consignment($user, 'ACTIVE-MATCH', ['customer_id' => $customer->id, 'vendor_id' => $vendor->id, 'origin' => 'Kochi', 'destination' => 'Muscat', 'service' => 'Air Express']);
        $filters = ['number' => 'MATCH-AWB', 'customer' => 'Target Customer', 'vendor' => 'Target Vendor', 'origin' => 'Kochi', 'destination' => 'Muscat', 'service' => 'Air Express', 'date_from' => '2026-10-15', 'date_to' => '2026-10-15', 'delivered_from' => '2026-10-17', 'delivered_to' => '2026-10-17'];
        foreach ($filters as $key => $value) {
            $result = $this->get('/archived-data?'.http_build_query([$key => $value]))->assertOk()->viewData('bills');
            $expected = in_array($key, ['date_from', 'delivered_from']) ? [$otherBill->id, $bill->id] : [$bill->id];
            $this->assertSame($expected, $result->getCollection()->modelKeys(), $key);
        }
        $this->get('/archived-data?'.http_build_query($filters))->assertOk()->assertSee('MATCH-AWB')->assertDontSee('ACTIVE-MATCH')->assertDontSee('OTHER-AWB');
        $this->get('/archived-data?q=Target')->assertOk()->assertSee('MATCH-AWB')->assertDontSee('ACTIVE-MATCH');
        $this->get('/archived-data?service=missing')->assertOk()->assertDontSee('MATCH-AWB');
    }

    public function test_filters_and_page_size_are_preserved_across_pagination_links(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $customer = Customer::factory()->create(['name' => 'Retained Customer']);
        $vendor = Vendor::factory()->create(['name' => 'Retained Vendor']);
        for ($i = 0; $i < 26; $i++) {
            [$shipment] = $this->consignment($user, 'KEEP-'.$i, ['customer_id' => $customer->id, 'vendor_id' => $vendor->id]);
            $this->delivered($shipment);
        }
        $query = ['q' => 'KEEP', 'customer' => 'Retained', 'vendor' => 'Retained', 'origin' => 'Delhi', 'destination' => 'London', 'service' => 'Express', 'date_from' => '2026-10-01', 'date_to' => '2026-10-31', 'per_page' => 25];
        $response = $this->get('/archived-data?'.http_build_query($query))->assertOk();
        parse_str(parse_url($response->viewData('bills')->nextPageUrl(), PHP_URL_QUERY), $next);
        foreach ($query as $key => $value) {
            $this->assertSame((string) $value, $next[$key]);
        }
        $this->get('/archived-data?'.http_build_query($next))->assertOk()->assertViewHas('bills', fn ($page) => count($page) === 1);
    }

    public function test_invalid_page_sizes_and_date_ranges_are_rejected_and_to_only_dates_work(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach ([15, 0, -1, 1000, '25;DROP TABLE users'] as $size) {
            $this->getJson('/bills?'.http_build_query(['per_page' => $size]))->assertUnprocessable()->assertJsonValidationErrors('per_page');
        }
        $this->getJson('/archived-data?date_from=2026-11-10&date_to=2026-11-01')->assertUnprocessable()->assertJsonValidationErrors('date_to');
        $this->getJson('/archived-data?delivered_from=2026-11-10&delivered_to=2026-11-01')->assertUnprocessable()->assertJsonValidationErrors('delivered_to');
        $this->get('/archived-data?date_to=2026-11-01')->assertOk();
        $this->get('/archived-data?delivered_to=2026-11-01')->assertOk();
    }

    public function test_customer_vendor_staff_and_booking_option_searches_and_filters_work(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        Customer::factory()->create(['name' => 'Target Contact', 'email' => 'filter@example.test', 'phone' => '555-001', 'created_at' => '2026-10-15']);
        Customer::factory()->create(['name' => 'Unrelated Contact', 'email' => 'other@example.test']);
        Vendor::factory()->create(['name' => 'Target Vendor', 'email' => 'vendor-filter@example.test', 'phone' => '555-001', 'created_at' => '2026-10-15']);
        Vendor::factory()->create(['name' => 'Unrelated Vendor']);
        User::factory()->create(['name' => 'Target Staff', 'email' => 'staff-filter@example.test', 'role' => 'staff', 'created_at' => '2026-10-15']);
        User::factory()->create(['name' => 'Unrelated Staff', 'role' => 'staff']);
        BookingOption::factory()->create(['type' => 'service', 'name' => 'Target Service', 'enabled' => false, 'created_at' => '2026-10-15']);
        BookingOption::factory()->create(['type' => 'service', 'name' => 'Unrelated Service', 'enabled' => true]);
        $this->get('/customers?email=filter&phone=555&date_from=2026-10-01&date_to=2026-10-31')->assertOk()->assertSee('Target Contact')->assertDontSee('Unrelated Contact');
        $this->get('/vendors?q=vendor-filter&phone=555')->assertOk()->assertSee('Target Vendor')->assertDontSee('Unrelated Vendor');
        $this->get('/staff?q=staff-filter&date_from=2026-10-01&date_to=2026-10-31')->assertOk()->assertSee('Target Staff')->assertDontSee('Unrelated Staff');
        $this->get('/booking-options?type=service&status=disabled&q=Target&date_to=2026-10-31')->assertOk()->assertSee('Target Service')->assertDontSee('Unrelated Service');
    }

    public function test_movement_history_is_paginated_filtered_and_scoped_to_its_own_consignment(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        [$shipment,$bill] = $this->consignment($user, 'HISTORY');
        [$other] = $this->consignment($user, 'OTHER-HISTORY');
        for ($i = 0; $i < 11; $i++) {
            $shipment->updates()->create(['created_by' => $user->id, 'status' => 'In transit', 'location' => 'Hub', 'message' => 'MOVE-'.$i, 'occurred_at' => '2026-10-17 12:00:00']);
        }
        $other->updates()->create(['created_by' => $user->id, 'status' => 'Delivered', 'location' => 'Other hub', 'message' => 'DO NOT LEAK', 'occurred_at' => '2026-10-17 12:00:00']);
        foreach (['/bills/'.$bill->id, '/shipments/'.$shipment->id] as $url) {
            $this->get($url)->assertOk()->assertViewHas('updates', fn ($page) => $page->perPage() === 10 && count($page) === 10)->assertDontSee('DO NOT LEAK');
            $this->get($url.'?per_page=25')->assertOk()->assertViewHas('updates', fn ($page) => count($page) === 11);
            $this->get($url.'?q=MOVE-1&location=Hub&status=In%20transit&date_from=2026-10-17&date_to=2026-10-17')->assertOk()->assertViewHas('updates', fn ($page) => count($page) === 2);
        }
    }

    public function test_search_for_zero_is_not_ignored_and_untrusted_search_is_escaped(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        [$first,$bill] = $this->consignment($user, 'NUMBER-0', ['reference' => 'ALPHA-REF']);
        $this->consignment($user, 'NUMBER-X', ['reference' => 'BETA-REF']);
        $this->get('/bills?number=0')->assertOk()->assertViewHas('bills', fn ($page) => $page->total() === 1);
        $this->get('/bills?q='.rawurlencode('<script>search()</script>'))->assertOk()->assertDontSee('<script>search()</script>', false)->assertSee('<script>search()</script>');
    }
}
