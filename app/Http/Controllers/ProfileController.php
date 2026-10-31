<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=2000,max_height=2000'], 'remove_photo' => 'sometimes|boolean']);
        $user = $request->user();
        $oldPhoto = $user->photo_path;
        $newPhoto = null;
        if ($request->hasFile('photo')) {
            $newPhoto = $request->file('photo')->store('profile-photos', 'local');
            if (! $newPhoto) {
                throw ValidationException::withMessages(['photo' => 'The photo could not be saved. Please try again.']);
            }
        }
        $user->name = $data['name'];
        if ($newPhoto) {
            $user->photo_path = $newPhoto;
        } elseif ($request->boolean('remove_photo')) {
            $user->photo_path = null;
        }
        try {
            $user->save();
        } catch (Throwable $exception) {
            if ($newPhoto) {
                Storage::disk('local')->delete($newPhoto);
            } throw $exception;
        }
        if ($oldPhoto && $oldPhoto !== $user->photo_path) {
            Storage::disk('local')->delete($oldPhoto);
        }

        return back()->with('success', 'Your profile has been updated.');
    }

    public function photo(Request $request): StreamedResponse
    {
        $path = $request->user()->photo_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate(['current_password' => 'required|current_password', 'password' => ['required', 'confirmed', 'different:current_password', 'max:72', PasswordRule::min(12)->letters()->numbers()]]);
        $user = $request->user();
        $user->password = Hash::make($data['password']);
        $user->setRememberToken(Str::random(60));
        $user->save();
        Password::deleteToken($user);
        $request->session()->regenerate();

        return back()->with('success', 'Password changed. Other signed-in sessions will need to sign in again.');
    }
}
