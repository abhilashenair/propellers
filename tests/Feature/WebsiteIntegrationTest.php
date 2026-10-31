<?php

namespace Tests\Feature;

use App\Models\AirwayBill;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WebsiteIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_integration_settings_are_admin_only_and_code_is_copyable_on_the_dashboard(): void
    {
        $this->get('/settings/website-integration')->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->get('/settings/website-integration')->assertForbidden();
        $this->get('/dashboard')->assertOk()->assertDontSee('Track a shipment')->assertDontSee('data-copy-embed', false);
        $this->flushSession();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get('/settings/website-integration')->assertOk()->assertSee('Website integration')->assertSee('Copy embed code')->assertSee('&lt;iframe', false)->assertSee(route('tracking.embed'))->assertSee('sandbox="allow-forms allow-same-origin"', false);
        $this->get('/dashboard')->assertOk()->assertSee('Copy embed code')->assertDontSee('Track a shipment')->assertSee('website-integration');
    }

    public function test_widget_is_public_and_hides_customer_contacts_charges_and_workspace_even_for_admin(): void
    {
        $shipment = Shipment::factory()->create(['number' => 'EMBED-001']);
        $customer = Customer::factory()->create(['name' => 'PRIVATE CUSTOMER', 'phone' => 'PRIVATE PHONE', 'address' => 'PRIVATE ADDRESS']);
        AirwayBill::factory()->create(['number' => $shipment->number, 'customer_id' => $customer->id, 'freight' => 98765]);
        $shipment->updates()->create(['created_by' => $shipment->created_by, 'status' => 'In transit', 'location' => 'Dubai hub', 'message' => 'Departed airport', 'occurred_at' => now()]);
        $this->get('/embed/tracking?number=EMBED-001')->assertOk()->assertHeader('Content-Security-Policy', 'frame-ancestors *')->assertHeader('Referrer-Policy', 'no-referrer')->assertSee('Departed airport')->assertSee('Dubai hub')->assertDontSee('PRIVATE CUSTOMER')->assertDontSee('PRIVATE PHONE')->assertDontSee('PRIVATE ADDRESS')->assertDontSee('98765')->assertDontSee('LOGISTICS WORKSPACE')->assertDontSee('Sign in');
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/embed/tracking?number=EMBED-001')->assertOk()->assertDontSee('ADMINISTRATION')->assertDontSee('Booking options')->assertSee('EMBED-001');
        $this->get('/embed/tracking?number=missing')->assertOk()->assertSee('Shipment not found');
    }

    public function test_company_logo_replaces_dashboard_brand_and_is_available_to_embedded_widget(): void
    {
        Storage::fake('local');
        $path = UploadedFile::fake()->image('company.png')->store('company-logos', 'local');
        CompanySetting::factory()->create(['id' => 1, 'name' => 'Acme Shipping', 'logo_path' => $path]);
        $this->get('/embed/company-logo')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('/embed/tracking')->assertOk()->assertSee('Acme Shipping logo')->assertSee(route('tracking.embed-logo'));
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk()->assertSee('Acme Shipping logo')->assertSee(route('company.logo'))->assertDontSee('brand-mark', false);
    }

    public function test_navigation_uses_accordion_groups_and_opens_the_active_settings_group(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get('/settings/website-integration')->assertOk()->assertSee('name="workspace-navigation"', false)->assertSee('<summary>', false)->assertSee('class="nav-group" name="workspace-navigation"  open ', false)->assertSee('Website integration')->assertSee('Email settings')->assertSee('Company details');
    }

    public function test_profile_email_is_explicitly_locked_and_cannot_be_changed_by_a_forged_submission(): void
    {
        $user = User::factory()->create(['email' => 'fixed@example.test']);
        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('Login email (cannot be changed)')->assertSee('readonly aria-readonly="true"', false);
        $this->put('/profile', ['name' => 'New Name', 'email' => 'changed@example.test'])->assertRedirect();
        $this->assertSame('fixed@example.test', $user->fresh()->email);
        $this->assertSame('New Name', $user->fresh()->name);
    }
}
