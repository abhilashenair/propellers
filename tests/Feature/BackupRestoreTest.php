<?php

namespace Tests\Feature;

use App\Models\AirwayBill;
use App\Models\Customer;
use App\Models\EmailSetting;
use App\Models\User;
use Illuminate\Encryption\Encrypter;
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

    public function test_password_backup_restores_between_different_application_keys(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin', 'password' => 'StrongPassword123']);
        $this->actingAs($admin);
        $customer = Customer::factory()->create(['name' => 'Transfer customer']);
        $settings = EmailSetting::factory()->create(['id' => 1, 'password' => 'Source SMTP secret']);
        $this->get('/backups')->assertOk()->assertDontSee('Download SQL backup');
        $backup = $this->post('/backups/download', ['backup_password' => 'TransferPassword123', 'backup_password_confirmation' => 'TransferPassword123'])->assertOk()->getContent();
        $this->assertStringNotContainsString('Source SMTP secret', $backup);
        $customer->update(['name' => 'Destination customer']);
        $oldEncrypter = Crypt::getFacadeRoot();
        try {
            Crypt::swap(new Encrypter(random_bytes(32), 'aes-256-cbc'));
            $this->post('/backups/restore', ['password' => 'StrongPassword123', 'backup_password' => 'TransferPassword123', 'confirm_restore' => '1', 'backup' => UploadedFile::fake()->createWithContent('transfer.backup', $backup)])->assertRedirect('/login');
            $this->assertGuest();
            $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'Transfer customer']);
            $this->assertSame('Source SMTP secret', $settings->fresh()->password);
            $this->assertSame(rtrim(config('app.url'), '/'), $settings->fresh()->application_url);
            $this->assertCount(1, Storage::disk('local')->files('backups'));
        } finally {
            Crypt::swap($oldEncrypter);
        }
    }

    public function test_portable_backup_rejects_wrong_password_and_tampering(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['role' => 'admin', 'password' => 'StrongPassword123']));
        $customer = Customer::factory()->create(['name' => 'Keep current data']);
        $backup = $this->post('/backups/download', ['backup_password' => 'TransferPassword123', 'backup_password_confirmation' => 'TransferPassword123'])->assertOk()->getContent();
        $envelope = json_decode($backup, true);
        $envelope['data'][20] = $envelope['data'][20] === 'A' ? 'B' : 'A';
        foreach ([[$backup, 'IncorrectPassword'], [$backup, ''], [json_encode($envelope), 'TransferPassword123']] as [$contents, $password]) {
            $this->travel(61)->seconds();
            $this->post('/backups/restore', ['password' => 'StrongPassword123', 'backup_password' => $password, 'confirm_restore' => '1', 'backup' => UploadedFile::fake()->createWithContent('transfer.backup', $contents)])->assertRedirect()->assertSessionHasErrors('backup');
            $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'Keep current data']);
        }
        $this->assertSame([], Storage::disk('local')->files('backups'));
        $this->assertNull(session()->getOldInput('backup_password'));
    }

    public function test_transfer_password_must_be_long_and_confirmed_without_flashing_it(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->post('/backups/download', ['backup_password' => 'short', 'backup_password_confirmation' => 'different'])->assertSessionHasErrors('backup_password');
        $this->assertNull(session()->getOldInput('backup_password'));
        $this->assertNull(session()->getOldInput('backup_password_confirmation'));
    }
}
