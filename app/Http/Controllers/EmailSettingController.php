<?php

namespace App\Http\Controllers;

use App\Models\EmailSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmailSettingController extends Controller
{
    public function edit(): View
    {
        return view('email-settings', ['settings' => EmailSetting::find(1)]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['enabled' => 'required|boolean', 'host' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9.\-]+$/'], 'port' => 'required|integer|min:1|max:65535', 'encryption' => 'required|in:starttls,smtps,none', 'username' => 'nullable|string|max:255', 'password' => 'nullable|string|max:4096', 'from_address' => 'required|email|max:255', 'from_name' => 'required|string|max:255', 'application_url' => ['required', 'url:http,https', 'max:2048']]);
        $url = parse_url($data['application_url']);
        if (isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment'])) {
            throw ValidationException::withMessages(['application_url' => 'Use your application base URL without credentials, query parameters or fragments.']);
        }
        $settings = EmailSetting::find(1) ?? new EmailSetting;
        $settings->id = 1;
        if (empty($data['password'])) {
            unset($data['password']);
        }
        if (! empty($data['username']) && empty($data['password']) && ! $settings->password) {
            throw ValidationException::withMessages(['password' => 'Enter the SMTP password for this username.']);
        }
        $data['application_url'] = rtrim($data['application_url'], '/');
        $settings->fill($data)->save();

        return redirect()->route('email-settings.edit')->with('success', 'Email settings saved. Enabled SMTP settings will be used for password-reset emails.');
    }
}
