<?php

namespace App\Http\Controllers;

use App\Models\CompanySetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class CompanySettingController extends Controller
{
    public function edit(): View
    {
        return view('settings', ['company' => CompanySetting::find(1)]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:2000'],
            'gstin' => ['nullable', 'string', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/i'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=2000,max_height=2000'],
            'remove_logo' => ['sometimes', 'boolean'],
        ], ['gstin.regex' => 'Enter a valid 15-character GSTIN.']);
        $company = CompanySetting::find(1) ?? new CompanySetting;
        $company->id = 1;
        $oldLogo = $company->logo_path;
        $newLogo = null;
        if ($request->hasFile('logo')) {
            $newLogo = $request->file('logo')->store('company-logos', 'local');
            if (! $newLogo) {
                throw ValidationException::withMessages(['logo' => 'The logo could not be saved. Please try again.']);
            }
        }
        $company->fill(['name' => $data['name'], 'address' => $data['address'], 'gstin' => isset($data['gstin']) ? strtoupper($data['gstin']) : null]);
        if ($newLogo) {
            $company->logo_path = $newLogo;
        } elseif ($request->boolean('remove_logo')) {
            $company->logo_path = null;
        }
        try {
            $company->save();
        } catch (Throwable $exception) {
            if ($newLogo) {
                Storage::disk('local')->delete($newLogo);
            }
            throw $exception;
        }
        if ($oldLogo && $oldLogo !== $company->logo_path) {
            Storage::disk('local')->delete($oldLogo);
        }

        return redirect()->route('settings.edit')->with('success', 'Company details saved. Airway bills now use these details.');
    }

    public function logo(): StreamedResponse
    {
        $company = CompanySetting::find(1);
        abort_unless($company?->logo_path && Storage::disk('local')->exists($company->logo_path), 404);

        return Storage::disk('local')->response($company->logo_path, null, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-cache']);
    }
}
