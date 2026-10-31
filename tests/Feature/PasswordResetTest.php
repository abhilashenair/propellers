<?php

namespace Tests\Feature;

use App\Models\EmailSetting;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_requests_use_saved_smtp_and_generic_responses_and_trusted_base_url(): void
    {
        Notification::fake();
        EmailSetting::factory()->create(['id' => 1]);
        $user = User::factory()->create();
        $this->get('/login')->assertOk()->assertSee('Forgot password?');
        $this->get('/forgot-password')->assertOk();
        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect()->assertSessionHas('success', 'If an account exists for that email, a password-reset link will be sent.');
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $url = $notification->toMail($user)->actionUrl;

            return str_starts_with($url, 'https://example.test/propeller/reset-password/') && str_contains($url, rawurlencode($user->email));
        });
        $this->assertSame('company-smtp', config('mail.default'));
        $this->post('/forgot-password', ['email' => 'unknown@example.test'])->assertRedirect()->assertSessionHas('success', 'If an account exists for that email, a password-reset link will be sent.');
    }

    public function test_reset_is_unavailable_when_smtp_is_disabled_without_logging_tokens(): void
    {
        Notification::fake();
        EmailSetting::factory()->create(['id' => 1, 'enabled' => false]);
        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasErrors('email');
        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_reset_link_is_single_use_and_changes_only_the_matching_account(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OriginalPassword123')]);
        $other = User::factory()->create();
        $otherHash = $other->password;
        $token = Password::createToken($user);
        $this->get('/reset-password/'.$token.'?email='.rawurlencode($user->email))->assertOk()->assertSee('Reset password');
        $data = ['email' => $user->email, 'token' => $token, 'password' => 'ReplacementPassword123', 'password_confirmation' => 'ReplacementPassword123'];
        $this->post('/reset-password', $data)->assertRedirect('/login');
        $this->assertTrue(Hash::check('ReplacementPassword123', $user->fresh()->password));
        $this->assertSame($otherHash, $other->fresh()->password);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->post('/reset-password', array_replace($data, ['password' => 'AnotherPassword123', 'password_confirmation' => 'AnotherPassword123']))->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('ReplacementPassword123', $user->fresh()->password));
    }

    public function test_invalid_expired_or_wrong_account_tokens_and_weak_passwords_are_rejected(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OriginalPassword123')]);
        $other = User::factory()->create();
        $token = Password::createToken($user);
        $data = ['email' => $user->email, 'token' => $token, 'password' => 'ReplacementPassword123', 'password_confirmation' => 'ReplacementPassword123'];
        $this->post('/reset-password', array_replace($data, ['email' => $other->email]))->assertSessionHasErrors('email');
        $this->post('/reset-password', array_replace($data, ['token' => 'invalid']))->assertSessionHasErrors('email');
        $this->post('/reset-password', array_replace($data, ['password' => 'short', 'password_confirmation' => 'mismatch']))->assertSessionHasErrors('password');
        $this->travel(61)->minutes();
        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('OriginalPassword123', $user->fresh()->password));
    }

    public function test_reset_endpoint_rate_limits_requests(): void
    {
        Notification::fake();
        EmailSetting::factory()->create(['id' => 1]);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password', ['email' => 'unknown@example.test'])->assertRedirect();
        }
        $this->post('/forgot-password',['email' => 'unknown@example.test'])->assertTooManyRequests();
    }
}
