<?php

namespace App\Http\Controllers;

use App\Models\EmailSetting;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Throwable;

class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => 'required|email|max:255']);
        if (! EmailSetting::configureMailer()) {
            return back()->withErrors(['email' => 'Password-reset email is not enabled. Contact your administrator.'])->onlyInput('email');
        }
        try {
            Password::sendResetLink($data);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['email' => 'Unable to send a reset email right now. Contact your administrator.'])->onlyInput('email');
        }

        return back()->with('success', 'If an account exists for that email, a password-reset link will be sent.');
    }

    public function edit(Request $request, string $token): View
    {
        return view('reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['token' => 'required|string|max:255', 'email' => 'required|email|max:255', 'password' => ['required', 'confirmed', 'max:72', PasswordRule::min(12)->letters()->numbers()]]);
        $status = Password::reset($data, function (User $user, string $password): void {
            $user->password = Hash::make($password);
            $user->setRememberToken(Str::random(60));
            $user->save();
            event(new PasswordReset($user));
        });
        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('success', 'Password reset successfully. Sign in using your new password.');
        }

        return back()->withErrors(['email' => 'This password-reset link is invalid or has expired. Request a new link.'])->onlyInput('email');
    }
}
