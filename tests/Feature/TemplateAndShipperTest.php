<?php

namespace Tests\Feature;

use App\Models\AirwayBill;
use App\Models\BookingOption;
use App\Models\Customer;
use App\Models\TemplateSetting;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateAndShipperTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        foreach (['origin' => 'Delhi', 'destination' => 'London', 'service' => 'Express'] as $type => $name) {
            BookingOption::factory()->create(['type' => $type, 'name' => $name]);
        }

        return ['number' => 'POSTAL-001', 'vendor_id' => Vendor::factory()->create()->id, 'customer_id' => Customer::factory()->create()->id, 'bill_date' => now()->toDateString(), 'origin' => 'Delhi', 'destination' => 'London', 'service' => 'Express', 'shipper_name' => 'Independent Shipper', 'shipper_phone' => '+91 0123456789', 'shipper_address' => 'Shipper Street, Delhi', 'shipper_postal_code' => '001234', 'shipper_tax_number' => 'SHIPPER-GST', 'consignee_name' => 'UK Recipient', 'consignee_phone' => '+44 7700 123456', 'consignee_address' => 'Recipient Road, London', 'consignee_postal_code' => 'HP13 6SZ', 'contents' => 'Clothes', 'pieces' => 1, 'weight' => 2, 'volumetric_weight' => 1, 'chargeable_weight' => 2, 'declared_value' => 500, 'currency' => 'INR', 'freight' => 100, 'other_charges' => 0, 'tax_rate' => 18];
    }

    public function test_only_admin_can_save_template_terms_and_settings_menu_links_to_the_form(): void
    {
        $this->get('/settings/template')->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->get('/settings/template')->assertForbidden();
        $this->put('/settings/template', ['terms_and_conditions' => 'Wrong role'])->assertForbidden();
        $this->assertDatabaseCount('template_settings', 0);
        $this->flushSession();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get('/settings')->assertOk()->assertSee('Template settings');
        $this->put('/settings/template', ['terms_and_conditions' => "First condition.\nSecond condition."])->assertRedirect('/settings/template');
        $this->assertDatabaseHas('template_settings', ['id' => 1, 'terms_and_conditions' => "First condition.\nSecond condition."]);
        $this->get('/settings/template')->assertOk()->assertSee('Terms and Conditions')->assertSee('First condition.');
        $this->put('/settings/template', ['terms_and_conditions' => ''])->assertRedirect('/settings/template');
        $this->assertDatabaseHas('template_settings', ['id' => 1, 'terms_and_conditions' => '']);
        $this->assertDatabaseCount('template_settings', 1);
    }

    public function test_shipper_details_both_phones_postcodes_and_terms_appear_in_printable_bill(): void
    {
        $this->actingAs(User::factory()->create());
        $payload = $this->payload();
        $customer = Customer::findOrFail($payload['customer_id']);
        $originalName = $customer->name;
        TemplateSetting::factory()->create(['id' => 1, 'terms_and_conditions' => "First saved condition.\nSecond saved condition."]);
        $this->post('/bills', $payload + ['shipper_details' => ['name' => 'Forged snapshot'], 'terms_and_conditions' => 'Forged terms'])->assertRedirect();
        $bill = AirwayBill::firstOrFail();
        $this->assertSame('Independent Shipper', $bill->shipper_details['name']);
        $this->assertSame('001234', $bill->shipper_details['postal_code']);
        $this->assertSame('HP13 6SZ', $bill->consignee_postal_code);
        $this->assertSame($originalName, $customer->fresh()->name);
        $this->get('/bills/'.$bill->id)->assertOk()->assertSee('Independent Shipper')->assertSee('+91 0123456789')->assertSee('001234')->assertSee('+44 7700 123456')->assertSee('HP13 6SZ')->assertSee('Shipper Street, Delhi')->assertSee('First saved condition.')->assertSee('Second saved condition.')->assertDontSee('Forged snapshot')->assertDontSee('Forged terms');
    }

    public function test_both_phone_numbers_and_postcodes_are_required_and_invalid_contact_data_saves_nothing(): void
    {
        $this->actingAs(User::factory()->create());
        $payload = $this->payload();
        foreach (['shipper_name', 'shipper_address', 'shipper_phone', 'shipper_postal_code', 'consignee_phone', 'consignee_postal_code'] as $field) {
            $this->post('/bills', array_replace($payload, [$field => '']))->assertSessionHasErrors($field);
        }
        $this->post('/bills', array_replace($payload, ['shipper_postal_code' => str_repeat('A', 21)]))->assertSessionHasErrors('shipper_postal_code');
        $this->post('/bills', array_replace($payload, ['consignee_postal_code' => ['invalid']]))->assertSessionHasErrors('consignee_postal_code');
        $this->assertDatabaseCount('airway_bills', 0);
        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_saved_bill_keeps_shipper_and_terms_after_customer_and_template_changes(): void
    {
        $this->actingAs(User::factory()->create());
        $payload = $this->payload();
        $template = TemplateSetting::factory()->create(['id' => 1, 'terms_and_conditions' => 'Original agreement.']);
        $this->post('/bills', $payload)->assertRedirect();
        $bill = AirwayBill::firstOrFail();
        $template->update(['terms_and_conditions' => 'New agreement.']);
        Customer::findOrFail($payload['customer_id'])->update(['name' => 'Changed Customer', 'phone' => 'CHANGED-PHONE', 'postal_code' => '999999']);
        $this->get('/bills/'.$bill->id)->assertOk()->assertSee('Original agreement.')->assertDontSee('New agreement.')->assertSee('Independent Shipper')->assertSee('001234')->assertDontSee('CHANGED-PHONE');
    }

    public function test_old_bill_without_postcodes_is_still_viewable_and_uses_template_terms(): void
    {
        $this->actingAs(User::factory()->create());
        TemplateSetting::factory()->create(['id' => 1, 'terms_and_conditions' => 'Terms for legacy bill.']);
        $bill = AirwayBill::factory()->create(['consignee_postal_code' => null, 'terms_and_conditions' => null, 'shipper_details' => ['name' => 'Legacy Shipper', 'address' => 'Legacy Address', 'phone' => '123456', 'tax_number' => null]]);
        $this->get('/bills/'.$bill->id)->assertOk()->assertSee('Legacy Shipper')->assertSee('Terms for legacy bill.')->assertSee('ZIP / postal code: —');
    }

    public function test_template_text_is_escaped_and_oversized_descriptions_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->put('/settings/template', ['terms_and_conditions' => str_repeat('A', 20001)])->assertSessionHasErrors('terms_and_conditions');
        $this->put('/settings/template', ['terms_and_conditions' => "<script>bad()</script>\nSecond line"])->assertRedirect('/settings/template');
        $bill = AirwayBill::factory()->create(['terms_and_conditions' => null]);
        $this->get('/bills/'.$bill->id)->assertOk()->assertSee('<script>bad()</script>')->assertDontSee('<script>bad()</script>', false)->assertSee('Second line');
    }

    public function test_creation_form_contains_editable_shipper_fields_and_required_postcodes_and_restores_input(): void
    {
        $this->actingAs(User::factory()->create());
        Customer::factory()->create(['name' => 'Customer Name', 'postal_code' => '001100']);
        $this->withSession(['_old_input' => ['shipper_name' => 'Edited Shipper', 'shipper_postal_code' => '007700', 'consignee_postal_code' => 'SW1A 1AA']])->get('/bills/create')->assertOk()->assertSee('Shipper details')->assertSee('name="shipper_name"', false)->assertSee('name="shipper_phone"', false)->assertSee('name="shipper_postal_code"', false)->assertSee('name="consignee_postal_code"', false)->assertSee('value="Edited Shipper"', false)->assertSee('value="007700"', false)->assertSee('data-postal-code="001100"', false)->assertSee('bill-shipper.js');
    }

    public function test_template_and_new_contact_fields_survive_sql_backup_and_never_appear_in_public_tracking(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $payload = $this->payload();
        TemplateSetting::factory()->create(['id' => 1, 'terms_and_conditions' => 'Backup agreement.']);
        $this->post('/bills', $payload)->assertRedirect();
        $response = $this->post('/backup')->assertOk();
        $restored = new \PDO('sqlite::memory:');
        $restored->exec($response->streamedContent());
        $this->assertSame('Backup agreement.', $restored->query('SELECT terms_and_conditions FROM template_settings')->fetchColumn());
        $this->assertSame('HP13 6SZ', $restored->query('SELECT consignee_postal_code FROM airway_bills')->fetchColumn());
        $this->post('/logout');
        $this->get('/embed/tracking?number=POSTAL-001')->assertOk()->assertDontSee('001234')->assertDontSee('HP13 6SZ')->assertDontSee('Independent Shipper')->assertDontSee('Backup agreement.');
    }

    public function test_customer_postcode_can_be_saved_for_future_shipper_prefill(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post('/customers', ['name' => 'Postal Customer', 'phone' => '0123456789', 'address' => 'Street, City', 'postal_code' => '001234'])->assertRedirect('/customers');
        $this->assertDatabaseHas('customers', ['name' => 'Postal Customer', 'postal_code' => '001234']);
        $this->get('/bills/create')->assertOk()->assertSee('data-postal-code="001234"', false);
    }

    public function test_bill_created_without_terms_does_not_acquire_terms_added_later(): void
    {
        $this->actingAs(User::factory()->create());
        $payload = $this->payload();
        $this->post('/bills', $payload)->assertRedirect();
        $bill = AirwayBill::firstOrFail();
        TemplateSetting::factory()->create(['id' => 1, 'terms_and_conditions' => 'Later agreement.']);
        $this->get('/bills/'.$bill->id)->assertOk()->assertDontSee('Later agreement.');
        $this->assertSame('', $bill->terms_and_conditions);
    }
}
