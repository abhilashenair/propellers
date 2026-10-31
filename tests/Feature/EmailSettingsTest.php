<?php

namespace Tests\Feature;

use App\Models\EmailSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmailSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function data(): array
    {
        return ['enabled' => 1, 'host' => 'smtp.example.test', 'port' => 587, 'encryption' => 'starttls', 'username' => 'sender@example.test', 'password' => 'SecretSmtpPassword123', 'from_address' => 'sender@example.test', 'from_name' => 'My Logistics', 'application_url' => 'https://example.test/propeller'];
    }

    public function test_only_admin_can_manage_smtp_settings(): void
    {
        $this->get('/settings/email')->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->get('/settings/email')->assertForbidden();
        $this->put('/settings/email', $this->data())->assertForbidden();
        $this->assertDatabaseCount('email_settings', 0);
    }

    public function test_smtp_password_is_encrypted_and_not_shown_or_replaced_by_a_blank_value(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->put('/settings/email', $this->data())->assertRedirect('/settings/email');
        $settings = EmailSetting::findOrFail(1);
        $this->assertSame('SecretSmtpPassword123', $settings->password);
        $this->assertNotSame('SecretSmtpPassword123', DB::table('email_settings')->value('password'));
        $this->assertArrayNotHasKey('password', $settings->toArray());
        $this->get('/settings/email')->assertOk()->assertSee('Password saved')->assertDontSee('SecretSmtpPassword123');
        $this->put('/settings/email', array_replace($this->data(), ['password' => '', 'port' => 465, 'encryption' => 'smtps']))->assertRedirect('/settings/email');
        $this->assertSame('SecretSmtpPassword123', $settings->fresh()->password);
        $this->assertDatabaseCount('email_settings', 1);
        $this->assertTrue(EmailSetting::configureMailer());
        $this->assertSame('smtps', config('mail.mailers.company-smtp.scheme'));
        $this->assertSame(465, config('mail.mailers.company-smtp.port'));
        $this->assertSame('sender@example.test', config('mail.from.address'));
    }

    public function test_disabled_smtp_settings_do_not_send_and_invalid_fields_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->put('/settings/email', array_replace($this->data(), ['host' => 'https://smtp.example.test', 'port' => 99999, 'from_address' => 'invalid', 'application_url' => 'javascript:bad']))->assertSessionHasErrors(['host', 'port', 'from_address', 'application_url']);
        $this->put('/settings/email', array_replace($this->data(), ['application_url' => 'https://user:password@example.test/propeller']))->assertSessionHasErrors('application_url');
        $this->put('/settings/email', array_replace($this->data(), ['password' => '']))->assertSessionHasErrors('password');
        $this->assertDatabaseCount('email_settings', 0);
        EmailSetting::factory()->create(['id' => 1, 'enabled' => false]);
        $this->assertFalse(EmailSetting::configureMailer());
    }

    public function test_sql_backup_contains_only_encrypted_smtp_password(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        EmailSetting::factory()->create(['id' => 1, 'password' => 'SecretSmtpPassword123']);
        $response = $this->post('/backup')->assertOk();
        $sql = $response->streamedContent();
        $this->assertStringNotContainsString('SecretSmtpPassword123', $sql);
        $restored = new \PDO('sqlite::memory:');
        $restored->exec($sql);
        $this->assertNotEmpty($restored->query('SELECT password FROM email_settings')->fetchColumn());
    }
}
