<?php

namespace App\Http\Controllers;

use App\Services\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Settings $settings): View
    {
        return view('settings.edit', [
            'groups' => collect($settings->overview())->groupBy('group'),
        ]);
    }

    public function update(Request $request, Settings $settings): RedirectResponse
    {
        foreach (Settings::DEFINITIONS as $key => $def) {
            if ($request->boolean("clear_{$key}")) {
                $settings->forget($key);

                continue;
            }

            $value = $request->input($key);

            if ($def['secret']) {
                // Blank means "leave the saved secret alone" — only replace when typed.
                if (filled($value)) {
                    $settings->set($key, $value);
                }

                continue;
            }

            // Non-secret: blank clears the override and falls back to .env.
            filled($value) ? $settings->set($key, $value) : $settings->forget($key);
        }

        return redirect()
            ->route('settings.edit')
            ->with('status', 'Settings saved.');
    }
}
