<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit()
    {
        return view('profile.edit', [
            'user' => request()->user()->load('passkeys'),
        ]);
    }

    public function disablePasskeys(Request $request)
    {
        $request->user()->passkeys()->delete();

        return back()->with('status', 'La connexion biométrique a été désactivée.');
    }

    public function consentPasskeys(Request $request)
    {
        $request->validate(['consent' => ['required', 'accepted']]);
        $request->user()->forceFill(['passkey_consent_at' => now()])->save();

        return response()->json(['status' => 'consent-recorded']);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $request->merge([
            'email' => mb_strtolower(trim((string) $request->email)),
            'phone' => trim((string) $request->phone) ?: null,
            'preferred_locale' => $request->input('preferred_locale', $user->preferred_locale ?: 'fr'),
        ]);
        $nameRules = $request->has('first_name') || $request->has('last_name') ? [
            'first_name' => ['required', 'string', 'min:1', 'max:60', "regex:/^[\pL\pM .'-]+$/u"],
            'last_name' => ['required', 'string', 'min:1', 'max:60', "regex:/^[\pL\pM .'-]+$/u"],
        ] : ['name' => ['required', 'string', 'min:2', 'max:120', "regex:/^[\pL\pM .'-]+$/u"]];
        $data = $request->validate($nameRules + [
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($request->user()->id)],
            'phone' => ['nullable', 'regex:/^\d{8}$/', function (string $attribute, mixed $value, \Closure $fail) use ($user) {
                if ($value !== '' && \App\Models\User::withTrashed()->where('phone', '+216'.$value)->where('id', '!=', $user->id)->exists()) {
                    $fail('Ce numéro de téléphone est déjà associé à un compte.');
                }
            }],
            'organization' => ['sometimes', 'nullable', 'string', 'max:120'],
            'preferred_locale' => ['required', Rule::in(['fr', 'en', 'ar'])],
            'about' => ['sometimes', 'nullable', 'string', 'max:500'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if (isset($data['first_name'], $data['last_name'])) {
            $data['name'] = trim($data['first_name'].' '.$data['last_name']);
            unset($data['first_name'], $data['last_name']);
        }
        $data['phone'] = $data['phone'] ? '+216'.$data['phone'] : null;
        if ($request->hasFile('profile_photo')) {
            $oldPhoto = $user->profile_photo_path;
            $data['profile_photo_path'] = $request->file('profile_photo')->store('profile-photos', 'public');
        }
        unset($data['profile_photo']);
        try {
            $user->update($data);
        } catch (UniqueConstraintViolationException $exception) {
            if (! empty($data['profile_photo_path'])) {
                Storage::disk('public')->delete($data['profile_photo_path']);
            }

            $errors = [];
            if ($data['phone'] && \App\Models\User::withTrashed()->where('phone', $data['phone'])->where('id', '!=', $user->id)->exists()) {
                $errors['phone'] = 'Ce numéro de téléphone est déjà associé à un compte.';
            }
            if (\App\Models\User::withTrashed()->where('email', $data['email'])->where('id', '!=', $user->id)->exists()) {
                $errors['email'] = 'Cette adresse e-mail est déjà associée à un compte.';
            }
            if ($errors === []) {
                $errors['email'] = 'Une information fournie est déjà utilisée par un autre compte.';
            }

            return back()->withInput($request->except('profile_photo'))->withErrors($errors);
        }
        if (! empty($oldPhoto)) {
            Storage::disk('public')->delete($oldPhoto);
        }

        return back()->with('status', 'Profil mis à jour.');
    }

    public function password(Request $request)
    {
        $data = $request->validate(['current_password' => ['required', 'current_password'], 'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()]]);
        $user = $request->user();
        $user->forceFill(['password' => $data['password'], 'session_version' => $user->session_version + 1, 'remember_token' => Str::random(60)])->save();
        auth()->logoutOtherDevices($data['password']);
        $request->session()->put('account_session_version', (int) $user->session_version);

        return back()->with('status', 'Mot de passe mis à jour.');
    }
}
