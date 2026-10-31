<?php

namespace Tests\Feature;

use App\Models\AirwayBill;
use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanySettingsTest extends TestCase
{
    use RefreshDatabase;

    private function details(): array
    {
        return ['name' => 'New Logistics Company', 'address' => "123 New Office Road\nMumbai 400001", 'gstin' => '27ABCDE1234F1Z5'];
    }

    private function bill(): AirwayBill
    {
        return AirwayBill::factory()->create(['shipper_details' => ['name' => 'Sample Shipper', 'address' => 'Shipper address', 'phone' => '1234567', 'tax_number' => null]]);
    }

    public function test_guests_and_staff_cannot_manage_company_settings(): void
    {
        $this->get('/settings')->assertRedirect('/login');
        $this->put('/settings', $this->details())->assertRedirect('/login');
        $this->get('/company/logo')->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->get('/settings')->assertForbidden();
        $this->put('/settings', $this->details())->assertForbidden();
        $this->assertDatabaseCount('company_settings', 0);
    }

    public function test_admin_saves_company_details_and_bill_header_uses_them(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $bill = $this->bill();
        $this->get('/settings')->assertOk()->assertSee('Company settings');
        $this->put('/settings', $this->details() + ['id' => 99, 'logo_path' => '/etc/passwd'])->assertRedirect('/settings')->assertSessionHas('success');
        $this->assertDatabaseHas('company_settings', $this->details() + ['id' => 1, 'logo_path' => null]);
        $this->get('/bills/'.$bill->id)->assertOk()->assertSee('New Logistics Company')->assertSee('123 New Office Road')->assertSee('GSTIN: 27ABCDE1234F1Z5')->assertDontSee('Mazyoona Muscat Trading')->assertDontSee('Mahipalpur');
        $this->get('/dashboard')->assertOk()->assertSee(route('settings.edit'));
    }

    public function test_company_details_can_be_updated_and_gstin_can_be_cleared(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        CompanySetting::factory()->create(['id' => 1, 'gstin' => '27ABCDE1234F1Z5']);
        $this->put('/settings', ['name' => 'Updated Company', 'address' => 'Updated Address', 'gstin' => ''])->assertRedirect('/settings');
        $this->assertDatabaseCount('company_settings', 1);
        $this->assertDatabaseHas('company_settings', ['id' => 1, 'name' => 'Updated Company', 'address' => 'Updated Address', 'gstin' => null]);
    }

    public function test_logo_can_be_uploaded_served_to_staff_and_displayed_on_bills(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->put('/settings', $this->details() + ['logo' => UploadedFile::fake()->image('company.png', 300, 100)])->assertRedirect('/settings');
        $company = CompanySetting::findOrFail(1);
        Storage::disk('local')->assertExists($company->logo_path);
        $this->get('/settings')->assertOk()->assertSee('Current company logo');
        $bill = $this->bill();
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->get('/company/logo')->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/bills/'.$bill->id)->assertOk()->assertSee('New Logistics Company logo')->assertSee(route('company.logo'));
    }

    public function test_replacing_logo_deletes_old_file_and_text_only_update_keeps_new_file(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->put('/settings', $this->details() + ['logo' => UploadedFile::fake()->image('old.png')])->assertRedirect('/settings');
        $oldPath = CompanySetting::findOrFail(1)->logo_path;
        $this->put('/settings', $this->details() + ['logo' => UploadedFile::fake()->image('new.jpg')])->assertRedirect('/settings');
        $newPath = CompanySetting::findOrFail(1)->logo_path;
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($newPath);
        $this->put('/settings', $this->details())->assertRedirect('/settings');
        $this->assertSame($newPath, CompanySetting::findOrFail(1)->logo_path);
        Storage::disk('local')->assertExists($newPath);
    }

    public function test_logo_can_be_removed_and_missing_logo_returns_not_found(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get('/company/logo')->assertNotFound();
        $this->put('/settings', $this->details() + ['logo' => UploadedFile::fake()->image('company.png')])->assertRedirect('/settings');
        $path = CompanySetting::findOrFail(1)->logo_path;
        $this->put('/settings', $this->details() + ['remove_logo' => 1])->assertRedirect('/settings');
        $this->assertNull(CompanySetting::findOrFail(1)->logo_path);
        Storage::disk('local')->assertMissing($path);
        $this->get('/company/logo')->assertNotFound();
    }

    public function test_invalid_company_details_and_logo_are_rejected_without_changing_settings(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->put('/settings', [])->assertSessionHasErrors(['name', 'address']);
        $this->put('/settings', array_replace($this->details(), ['gstin' => 'invalid']))->assertSessionHasErrors(['gstin' => 'Enter a valid 15-character GSTIN.']);
        $this->put('/settings', $this->details() + ['logo' => UploadedFile::fake()->create('logo.svg', 1, 'image/svg+xml')])->assertSessionHasErrors('logo');
        $this->put('/settings', $this->details() + ['logo' => UploadedFile::fake()->image('large.png')->size(2049)])->assertSessionHasErrors('logo');
        $this->put('/settings', $this->details() + ['logo' => UploadedFile::fake()->image('wide.png', 2001, 10)])->assertSessionHasErrors('logo');
        $this->assertDatabaseCount('company_settings', 0);
        Storage::disk('local')->assertDirectoryEmpty('company-logos');
    }

    public function test_unconfigured_bill_has_no_old_company_address(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $bill = $this->bill();
        $this->get('/bills/'.$bill->id)->assertOk()->assertSee('Company details not configured')->assertSee('Add company details in Settings')->assertDontSee('Mahipalpur')->assertDontSee('Mazyoona');
    }

    public function test_company_details_are_escaped_and_gstin_is_normalized(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->put('/settings', ['name' => '<script>company()</script>', 'address' => '<script>address()</script>', 'gstin' => '27abcde1234f1z5'])->assertRedirect('/settings');
        $bill = $this->bill();
        $this->get('/bills/'.$bill->id)->assertOk()->assertSee('<script>company()</script>')->assertSee('<script>address()</script>')->assertDontSee('<script>company()</script>', false)->assertDontSee('<script>address()</script>', false);
        $this->assertDatabaseHas('company_settings', ['gstin' => '27ABCDE1234F1Z5']);
    }

    public function test_sql_backup_restores_company_details(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        CompanySetting::factory()->create(['id' => 1] + $this->details());
        $response = $this->post('/backup')->assertOk()->assertDownload();
        $restored = new \PDO('sqlite::memory:');
        $restored->exec($response->streamedContent());
        $this->assertSame('New Logistics Company', $restored->query('SELECT name FROM company_settings WHERE id=1')->fetchColumn());
    }
}
