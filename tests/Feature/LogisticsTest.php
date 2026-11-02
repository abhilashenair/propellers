<?php

namespace Tests\Feature;

use App\Models\AirwayBill;
use App\Models\BookingOption;
use App\Models\Customer;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LogisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_track_but_cannot_access_operations(): void
    {
        $this->get('/')->assertOk()->assertSee('Your shipment. In sight.');
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/bills')->assertRedirect('/login');
        $this->post('/backup')->assertRedirect('/login');
    }

    public function test_login_accepts_valid_credentials_and_logout_ends_session(): void
    {
        $user = User::factory()->create(['email' => 'staff@example.test', 'password' => Hash::make('StrongPassword123')]);
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => $user->email, 'password' => 'StrongPassword123'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_staff_cannot_manage_staff_or_download_backup(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->get('/staff')->assertForbidden();
        $this->post('/staff', ['name' => 'Bad actor', 'email' => 'bad@example.test', 'password' => 'StrongPassword123', 'password_confirmation' => 'StrongPassword123', 'role' => 'admin'])->assertForbidden();
        $this->post('/backup')->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'bad@example.test']);
    }

    public function test_admin_creates_staff_without_allowing_role_escalation(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post('/staff', ['name' => 'Operator', 'email' => 'operator@example.test', 'password' => 'StrongPassword123', 'password_confirmation' => 'StrongPassword123', 'role' => 'admin'])->assertRedirect();
        $user = User::where('email', 'operator@example.test')->firstOrFail();
        $this->assertSame('staff', $user->role);
        $this->assertTrue(Hash::check('StrongPassword123', $user->password));
        $this->get('/staff')->assertOk()->assertSee('Operator');
    }

    public function test_staff_creates_and_updates_customer_and_vendor(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['customers', 'vendors'] as $resource) {
            $this->post('/'.$resource, ['name' => 'Acme', 'phone' => '123456', 'email' => 'acme@example.test', 'address' => 'Delhi 110037, India'])->assertRedirect('/'.$resource);
            $this->assertDatabaseHas($resource, ['name' => 'Acme']);
            $this->put('/'.$resource.'/1', ['name' => 'Updated Acme', 'phone' => '987654', 'address' => 'London, UK'])->assertRedirect('/'.$resource);
            $this->assertDatabaseHas($resource, ['name' => 'Updated Acme', 'phone' => '987654']);
            $this->get('/'.$resource)->assertOk()->assertSee('Updated Acme');
            $this->get('/'.$resource.'/1/edit')->assertOk()->assertSee('Updated Acme');
        }
    }

    public function test_airway_registration_and_public_tracking_exclude_bill_details(): void
    {
        $user = User::factory()->create();
        $vendor = Vendor::factory()->create();
        $this->actingAs($user)->post('/shipments', ['number' => 'AWB-100', 'vendor_id' => $vendor->id, 'origin' => 'Delhi', 'destination' => 'London'])->assertRedirect();
        $this->assertDatabaseHas('shipments', ['number' => 'AWB-100', 'vendor_id' => $vendor->id]);
        $this->post('/shipments/AWB-100/updates', ['status' => 'In transit', 'location' => 'Dubai', 'message' => 'Departed hub', 'occurred_at' => now()->subMinute()->format('Y-m-d H:i:s')])->assertRedirect();
        $this->assertDatabaseHas('tracking_updates', ['shipment_number' => 'AWB-100', 'status' => 'In transit']);
        $this->post('/logout');
        $this->get('/track?number=AWB-100')->assertOk()->assertSee('Departed hub')->assertSee('Dubai')->assertDontSee($user->email)->assertDontSee('Freight');
        $this->get('/track?number=missing')->assertOk()->assertSee('Shipment not found');
    }

    private function billPayload(): array
    {
        foreach (['origin' => 'Delhi', 'destination' => 'London', 'service' => 'UK Express'] as $type => $name) {
            BookingOption::factory()->create(['type' => $type, 'name' => $name]);
        }

        return ['number' => 'BILL-100', 'vendor_id' => Vendor::factory()->create()->id, 'customer_id' => Customer::factory()->create()->id, 'bill_date' => '2026-10-30', 'origin' => 'Delhi', 'destination' => 'London', 'consignee_name' => 'Private Recipient', 'consignee_phone' => '123456', 'consignee_postal_code' => 'HP13 6SZ', 'shipper_name' => 'Original Shipper', 'shipper_phone' => '+91 1234567890', 'shipper_postal_code' => '001234', 'shipper_address' => 'Original Shipper Address', 'consignee_address' => 'Private delivery address', 'contents' => 'Clothes', 'pieces' => 2, 'weight' => 17, 'chargeable_weight' => 17, 'volumetric_weight' => 12.01, 'declared_value' => 6410, 'dimensions' => '49 × 49 × 25', 'service' => 'UK Express', 'currency' => 'INR', 'freight' => '100.10', 'other_charges' => '20.20', 'tax_rate' => '18'];
    }

    public function test_bill_total_is_calculated_on_server_and_print_view_is_available(): void
    {
        $this->actingAs(User::factory()->create());
        $data = $this->billPayload();
        $this->post('/bills', $data + ['total_minor' => 1])->assertRedirect();
        $bill = AirwayBill::firstOrFail();
        $this->assertSame(14195, $bill->total_minor);
        $this->assertDatabaseHas('tracking_updates', ['shipment_number' => 'BILL-100', 'status' => 'Booked']);
        $this->get('/bills/'.$bill->id)->assertOk()->assertSee('AIRWAY BILL COPY')->assertSee('141.95')->assertSee('Private Recipient');
        $this->get('/dashboard')->assertOk()->assertSee('BILL-100');
        $this->get('/bills')->assertOk()->assertSee('BILL-100');
        $this->get('/bills/create')->assertOk()->assertSee('Create airway bill');
        $this->get('/shipments')->assertOk()->assertSee('BILL-100');
        $this->get('/shipments/'.Shipment::firstOrFail()->id)->assertOk()->assertSee('Tracking timeline');
        $this->post('/logout');
        $this->get('/track?number=BILL-100')->assertOk()->assertDontSee('Private Recipient')->assertDontSee('Private delivery address')->assertDontSee('141.95');
    }

    public function test_saved_bill_preserves_shipper_details_after_customer_changes(): void
    {
        $this->actingAs(User::factory()->create());
        $data = $this->billPayload();
        Customer::findOrFail($data['customer_id'])->update(['name' => 'Original Shipper']);
        $this->post('/bills', $data)->assertRedirect();
        Customer::findOrFail($data['customer_id'])->update(['name' => 'Changed Shipper']);
        $this->get('/bills/'.AirwayBill::firstOrFail()->id)->assertOk()->assertSee('Original Shipper')->assertDontSee('Changed Shipper');
    }

    public function test_chargeable_weight_cannot_be_less_than_actual_or_volumetric_weight(): void
    {
        $this->actingAs(User::factory()->create());
        $data = $this->billPayload();
        $this->post('/bills', array_replace($data, ['chargeable_weight' => 1]))->assertSessionHasErrors('chargeable_weight');
        $this->assertDatabaseCount('airway_bills', 0);
    }

    public function test_duplicate_bill_and_invalid_charges_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $data = $this->billPayload();
        $this->post('/bills', array_replace($data, ['freight' => '-1']))->assertSessionHasErrors('freight');
        $this->assertDatabaseCount('airway_bills', 0);
        $this->post('/bills', $data)->assertRedirect();
        $this->post('/bills', $data)->assertSessionHasErrors('number');
        $this->assertDatabaseCount('airway_bills', 1);
    }

    public function test_existing_airway_number_cannot_be_reassigned_to_another_vendor(): void
    {
        $this->actingAs(User::factory()->create());
        $data = $this->billPayload();
        Shipment::factory()->create(['number' => $data['number'], 'vendor_id' => Vendor::factory()->create()->id]);
        $this->post('/bills', $data)->assertSessionHasErrors('number');
        $this->assertDatabaseCount('airway_bills', 0);
    }

    public function test_tracking_rejects_invalid_status_and_future_events(): void
    {
        $this->actingAs(User::factory()->create());
        $shipment = Shipment::factory()->create();
        $this->post('/shipments/'.$shipment->number.'/updates', ['status' => 'Unknown', 'location' => 'Dubai', 'occurred_at' => now()->addDay()->format('Y-m-d H:i:s')])->assertSessionHasErrors(['status', 'occurred_at']);
        $this->assertDatabaseCount('tracking_updates', 0);
    }

    public function test_customer_input_is_escaped_in_views(): void
    {
        $this->actingAs(User::factory()->create());
        Customer::factory()->create(['name' => '<script>alert(1)</script>']);
        $this->get('/customers')->assertOk()->assertSee('<script>alert(1)</script>')->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_admin_backup_can_be_restored_with_records_and_schema(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $shipment = Shipment::factory()->create(['created_by' => auth()->id()]);
        $customer = Customer::factory()->create(['name' => "O'Reilly Logistics"]);
        $response = $this->post('/backup')->assertOk()->assertDownload();
        $sql = $response->streamedContent();
        $restored = new \PDO('sqlite::memory:');
        $restored->exec($sql);
        $this->assertSame("O'Reilly Logistics", $restored->query('SELECT name FROM customers')->fetchColumn());
        $this->assertSame(1, (int) $restored->query('SELECT count(*) FROM users')->fetchColumn());
        $this->assertSame([], $restored->query('PRAGMA foreign_key_check')->fetchAll());
        $this->assertSame($shipment->number, $restored->query('SELECT number FROM shipments')->fetchColumn());
        $this->expectException(\PDOException::class);
        $restored->exec('INSERT INTO shipments SELECT NULL,number,vendor_id,created_by,origin,destination,created_at,updated_at FROM shipments');
    }

    public function test_missing_fields_are_rejected_without_saving_records(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/customers', [])->assertSessionHasErrors(['name', 'phone', 'address']);
        $this->post('/vendors', [])->assertSessionHasErrors(['name', 'phone', 'address']);
        $this->post('/shipments', [])->assertSessionHasErrors(['number', 'vendor_id', 'origin', 'destination']);
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('vendors', 0);
        $this->assertDatabaseCount('shipments', 0);
    }
}
