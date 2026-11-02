<?php

namespace App\Http\Controllers;

use App\Models\TemplateSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TemplateSettingController extends Controller
{
    public function edit(): View
    {
        return view('template-settings', ['settings' => TemplateSetting::find(1)]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['terms_and_conditions' => 'nullable|string|max:20000']);
        $settings = TemplateSetting::find(1) ?? new TemplateSetting;
        $settings->id = 1;
        $settings->terms_and_conditions = $data['terms_and_conditions'] ?? '';
        $settings->save();

        return redirect()->route('template-settings.edit')->with('success', 'Template settings saved. New bills will include these terms and conditions.');
    }
}
