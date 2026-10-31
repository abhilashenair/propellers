<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_is_private_and_updates_only_the_current_user(): void
    {
        $this->get('/profile')->assertRedirect('/login');
        $this->get('/profile/photo')->assertRedirect('/login');
        $user = User::factory()->create(['role' => 'staff']);
        $other = User::factory()->create();
        $this->actingAs($user)->get('/profile')->assertOk()->assertSee($user->email);
        $this->put('/profile', ['name' => 'Updated Name', 'id' => $other->id, 'email' => $other->email, 'role' => 'admin'])->assertRedirect();
        $this->assertSame('Updated Name', $user->fresh()->name);
        $this->assertSame('staff', $user->fresh()->role);
        $this->assertNotSame('Updated Name', $other->fresh()->name);
        $this->assertNotSame($other->email, $user->fresh()->email);
    }

    public function test_photo_upload_replacement_and_removal_are_private_to_the_user(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->get('/profile/photo')->assertNotFound();
        $this->put('/profile', ['name' => $user->name, 'photo' => UploadedFile::fake()->image('photo.png')])->assertRedirect();
        $old = $user->fresh()->photo_path;
        Storage::disk('local')->assertExists($old);
        $this->get('/profile/photo')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->put('/profile', ['name' => $user->name, 'photo' => UploadedFile::fake()->image('new.jpg')])->assertRedirect();
        $new = $user->fresh()->photo_path;
        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists($new);
        $this->put('/profile', ['name' => $user->name])->assertRedirect();
        $this->assertSame($new, $user->fresh()->photo_path);
        $this->put('/profile', ['name' => $user->name, 'remove_photo' => 1])->assertRedirect();
        Storage::disk('local')->assertMissing($new);
        $this->assertNull($user->fresh()->photo_path);
        $this->flushSession();
        $this->actingAs(User::factory()->create());
        $this->get('/profile/photo')->assertNotFound();
    }

    public function test_invalid_photos_and_names_are_rejected(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->put('/profile', [])->assertSessionHasErrors('name');
        $this->put('/profile', ['name' => 'Valid', 'photo' => UploadedFile::fake()->create('bad.svg', 10, 'image/svg+xml')])->assertSessionHasErrors('photo');
        $this->put('/profile', ['name' => 'Valid', 'photo' => UploadedFile::fake()->image('large.png')->size(2049)])->assertSessionHasErrors('photo');
        $this->put('/profile', ['name' => 'Valid', 'photo' => UploadedFile::fake()->image('wide.png', 2001, 10)])->assertSessionHasErrors('photo');
        $this->assertNull($user->fresh()->photo_path);
    }

    public function test_password_change_requires_current_password_and_confirmation(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPassword123')]);
        $this->actingAs($user);
        $this->put('/profile/password', ['current_password' => 'WrongPassword', 'password' => 'NewPassword456', 'password_confirmation' => 'NewPassword456'])->assertSessionHasErrors('current_password');
        $this->put('/profile/password', ['current_password' => 'OldPassword123', 'password' => 'short', 'password_confirmation' => 'different'])->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('OldPassword123', $user->fresh()->password));
    }

    public function test_password_change_revokes_reset_tokens_and_other_sessions_but_keeps_current_session(): void
    {
        $user = User::factory()->create(['password' => Hash::make('OldPassword123')]);
        $this->actingAs($user)->get('/profile')->assertOk();
        $oldSessionHash = session('password_hash_web');
        $oldRemember = $user->remember_token;
        Password::createToken($user);
        $this->put('/profile/password', ['current_password' => 'OldPassword123', 'password' => 'NewPassword456', 'password_confirmation' => 'NewPassword456'])->assertRedirect();
        $this->assertTrue(Hash::check('NewPassword456', $user->fresh()->password));
        $this->assertNotSame($oldRemember, $user->fresh()->remember_token);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->get('/profile')->assertOk();
        $this->withSession(['password_hash_web' => $oldSessionHash])->get('/profile')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_admin_account_has_booking_options_shortcut_and_profile_navigation(): void
    {
        $user = User::factory()->create(['email' => 'abhilash@ormeon.com', 'role' => 'admin']);
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('Booking options: origins, destinations & services', false)->assertSee(route('booking-options.index'))->assertSee('My profile')->assertSee('Email settings');
        $this->get('/booking-options')->assertOk();
    }
}
