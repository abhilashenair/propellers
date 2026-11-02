<?php

namespace Tests\Feature;

use App\Models\AirwayBill;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_routes_require_an_admin(): void
    {
        $this->get('/backups')->assertRedirect('/login');
        $this->post('/backups/download')->assertRedirect('/login');
        $this->post('/backups/restore')->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->get('/backups')->assertForbidden();
        $this->post('/backups/download')->assertForbidden();
        $this->post('/backups/restore')->assertForbidden();
    }

    public function test_admin_can_restore_data_and_is_signed_out_with_a_recovery_copy_saved(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin', 'password' => 'StrongPassword123']);
        $this->actingAs($admin);
        $bill = AirwayBill::factory()->create(['created_by' => $admin->id]);
        $customer = Customer::factory()->create(['name' => 'Original customer']);
        $this->get('/backups')->assertOk()->assertSee('Your current admin password');
        $backup = $this->post('/backups/download')->assertOk()->assertDownload()->getContent();
        $this->assertStringNotContainsString('Original customer', $backup);
        $customer->update(['name' => 'Changed customer']);
        $extra = Customer::factory()->create(['name' => 'Added after backup']);
        $this->post('/backups/restore', ['password' => 'StrongPassword123', 'confirm_restore' => '1', 'backup' => UploadedFile::fake()->createWithContent('data.backup', $backup)])->assertRedirect('/login')->assertSessionHas('success');
        $this->assertGuest();
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'Original customer']);
        $this->assertDatabaseMissing('customers', ['id' => $extra->id]);
        $this->assertDatabaseHas('airway_bills', ['id' => $bill->id, 'number' => $bill->number]);
        $files = Storage::disk('local')->files('backups');
        $this->assertCount(1, $files);
        $recovery = json_decode(Crypt::decryptString(Storage::disk('local')->get($files[0])), true);
        $this->assertContains('Changed customer', array_column($recovery['tables']['customers'], 'name'));
    }

    public function test_wrong_password_and_missing_confirmation_do_not_restore(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['role' => 'admin', 'password' => 'StrongPassword123']));
        $customer = Customer::factory()->create();
        $this->post('/backups/restore', ['password' => 'wrong', 'backup' => UploadedFile::fake()->createWithContent('data.backup', 'invalid')])->assertSessionHasErrors(['password', 'confirm_restore']);
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
        $this->assertSame([], Storage::disk('local')->files('backups'));
    }

    public function test_invalid_or_sql_upload_does_not_change_records(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'password' => 'StrongPassword123']));
        $customer = Customer::factory()->create();
        $this->post('/backups/restore', ['password' => 'StrongPassword123', 'confirm_restore' => '1', 'backup' => UploadedFile::fake()->createWithContent('backup.sql', 'DROP TABLE customers;')])->assertSessionHasErrors('backup');
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    public function test_incompatible_backup_is_rejected_before_replacement(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin', 'password' => 'StrongPassword123']));
        $customer = Customer::factory()->create();
        $backup = $this->post('/backups/download')->assertOk()->getContent();
        $payload = json_decode(Crypt::decryptString($backup), true);
        unset($payload['tables']['customers'][0]['name']);
        $this->post('/backups/restore', ['password' => 'StrongPassword123', 'confirm_restore' => '1', 'backup' => UploadedFile::fake()->createWithContent('data.backup', Crypt::encryptString(json_encode($payload)))])->assertSessionHasErrors('backup');
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    public function test_constraint_failure_rolls_back_deleted_records(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['role' => 'admin', 'password' => 'StrongPassword123']));
        $customer = Customer::factory()->create(['name' => 'Keep me']);
        $backup = $this->post('/backups/download')->assertOk()->getContent();
        $payload = json_decode(Crypt::decryptString($backup), true);
        $payload['tables']['customers'][] = $payload['tables']['customers'][0];
        $this->post('/backups/restore', ['password' => 'StrongPassword123', 'confirm_restore' => '1', 'backup' => UploadedFile::fake()->createWithContent('data.backup', Crypt::encryptString(json_encode($payload)))])->assertSessionHasErrors('backup');
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'Keep me']);
        $this->assertSame(1, DB::table('users')->count());
        $this->assertCount(1, Storage::disk('local')->files('backups'));
    }
}
