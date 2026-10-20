<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\NewUserRegistration;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function login()
    {
        return view('auth.login');
    }

    public function register()
    {
        return view('auth.register');
    }

    private function verifyGoogleRecaptcha(Request $request): ?string
    {
        if (! config('services.google_recaptcha.enabled')) {
            return 'La vérification anti-robot est temporairement indisponible. Réessayez plus tard.';
        }

        $siteKey = config('services.google_recaptcha.site_key');
        $secret = config('services.google_recaptcha.secret');
        if (! $siteKey || ! $secret) {
            return 'La vérification anti-robot est temporairement indisponible. Réessayez plus tard.';
        }
        $captchaToken = (string) $request->input('g-recaptcha-response', '');
        if (trim($captchaToken) === '') {
            Log::notice('Google reCAPTCHA token was missing from the submitted form.', [
                'captcha_token_present' => false,
                'route_name' => $request->route()?->getName(),
            ]);

            return 'Veuillez cocher la case reCAPTCHA, puis réessayer.';
        }

        try {
            $response = Http::asForm()->timeout(5)->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secret,
                'response' => $captchaToken,
                'remoteip' => $request->ip(),
            ]);
        } catch (ConnectionException $exception) {
            $technicalMessage = preg_replace('/\s+for https?:\/\/\S+/i', '', $exception->getMessage()) ?? '';
            $technicalMessage = preg_replace('/[\r\n\t]+/', ' ', $technicalMessage) ?? '';
            foreach ([$secret, (string) $request->input('g-recaptcha-response'), (string) $request->ip()] as $sensitiveValue) {
                if ($sensitiveValue !== '') {
                    $technicalMessage = str_replace($sensitiveValue, '[redacted]', $technicalMessage);
                }
            }
            $technicalMessage = preg_replace('/\b[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}\b/', '[email redacted]', $technicalMessage) ?? '';
            $technicalMessage = mb_substr(trim($technicalMessage), 0, 300);
            $failureClass = match (true) {
                preg_match('/cURL error 28|timed? out|timeout/i', $technicalMessage) === 1 => 'timeout',
                preg_match('/cURL error 6|DNS|resolve host|name resolution/i', $technicalMessage) === 1 => 'dns',
                preg_match('/cURL error 60|certificate|SSL|TLS/i', $technicalMessage) === 1 => 'ssl_certificate',
                preg_match('/cURL error 7|connection refused|couldn.t connect|failed to connect/i', $technicalMessage) === 1 => 'connection_refused_or_unreachable',
                default => 'other',
            };

            Log::warning('Google reCAPTCHA verification request failed.', [
                'exception_type' => get_class($exception),
                'failure_class' => $failureClass,
                'curl_code' => preg_match('/cURL error\s+(\d+)/i', $exception->getMessage(), $curlMatch) === 1 ? (int) $curlMatch[1] : null,
                'http_status' => null,
                'google_error_codes' => [],
                'captcha_token_present' => true,
                'php_version' => PHP_VERSION,
                'php_sapi' => PHP_SAPI,
                'php_ini' => php_ini_loaded_file() ?: null,
                'technical_message' => $technicalMessage,
            ]);

            return 'La vérification reCAPTCHA est temporairement indisponible. Réessayez.';
        } catch (\Throwable $exception) {
            Log::warning('Google reCAPTCHA verification failed unexpectedly.', [
                'exception_type' => get_class($exception),
                'failure_class' => 'other',
                'http_status' => null,
                'google_error_codes' => [],
                'captcha_token_present' => true,
                'php_version' => PHP_VERSION,
                'php_sapi' => PHP_SAPI,
                'php_ini' => php_ini_loaded_file() ?: null,
            ]);

            return 'La vérification reCAPTCHA est temporairement indisponible. Réessayez.';
        }

        $googleErrorCodes = array_values(array_filter(
            (array) $response->json('error-codes', []),
            fn ($code) => is_string($code) && $code !== ''
        ));
        $googleSuccess = $response->json('success') === true;
        Log::info('Google reCAPTCHA verification response received.', [
            'http_status' => $response->status(),
            'google_success' => $googleSuccess,
            'google_error_codes' => $googleErrorCodes,
            'captcha_token_present' => true,
        ]);

        if (! $response->successful()) {
            Log::warning('Google reCAPTCHA returned an unsuccessful HTTP status.', [
                'http_status' => $response->status(),
            ]);

            return 'La vérification reCAPTCHA est temporairement indisponible. Réessayez.';
        }
        if (! $googleSuccess) {
            if (in_array('invalid-input-secret', $googleErrorCodes, true)) {
                return 'La vérification anti-robot a échoué. Rechargez la page et réessayez.';
            }
            if (in_array('timeout-or-duplicate', $googleErrorCodes, true)) {
                return 'La réponse reCAPTCHA a expiré. Rechargez la page et cochez à nouveau la case.';
            }

            return 'Google n’a pas validé reCAPTCHA. Vérifiez le domaine autorisé puis réessayez.';
        }

        return null;
    }

    public function storeRegistration(Request $request)
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->email)), 'phone' => trim((string) $request->phone)]);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120', "regex:/^[\pL\pM .'-]+$/u"],
            'role' => ['required', 'in:'.implode(',', User::PUBLIC_ROLES)],
            'phone' => ['required', 'regex:/^\d{8}$/', function (string $attribute, mixed $value, \Closure $fail) {
                if (User::withTrashed()->where('phone', '+216'.$value)->exists()) {
                    $fail('Ce numéro de téléphone est déjà associé à un compte.');
                }
            }],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()->symbols()],
            'terms' => ['accepted'],
        ], ['captcha' => 'Vérification reCAPTCHA requise.']);
        if ($captchaError = $this->verifyGoogleRecaptcha($request)) {
            return back()->withInput($request->except('password', 'password_confirmation'))->withErrors(['captcha' => $captchaError]);
        }

        try {
            $user = DB::transaction(fn () => User::create([
                'name' => trim($data['name']), 'email' => $data['email'], 'phone' => '+216'.$data['phone'],
                'role' => $data['role'], 'status' => 'pending', 'password' => $data['password'], 'terms_accepted_at' => now(), 'terms_version' => '2026-10',
            ]));
        } catch (UniqueConstraintViolationException $exception) {
            $errors = [];
            if (User::withTrashed()->where('phone', '+216'.$data['phone'])->exists()) {
                $errors['phone'] = 'Ce numéro de téléphone est déjà associé à un compte.';
            }
            if (User::withTrashed()->where('email', $data['email'])->exists()) {
                $errors['email'] = 'Cette adresse e-mail est déjà associée à un compte.';
            }
            if ($errors === []) {
                $errors['email'] = 'Un compte utilise déjà une de ces informations. Vérifiez l’adresse e-mail et le téléphone.';
            }

            return back()->withInput($request->except('password', 'password_confirmation', 'g-recaptcha-response'))->withErrors($errors);
        }
        $admins = User::where('role', 'admin')->where('status', 'approved')->get();
        foreach ($admins as $admin) {
            try {
                $admin->notify(new NewUserRegistration($user));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return redirect()->route('login')->with('status', 'Votre demande de création de compte a été envoyée. Votre compte sera accessible après vérification par l’administrateur. Vous recevrez un e-mail dès que votre compte sera approuvé.');
    }

    public function authenticate(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $email = mb_strtolower(trim($credentials['email']));
        $user = User::withTrashed()->where('email', $email)->first();
        if ($captchaError = $this->verifyGoogleRecaptcha($request)) {
            return back()->withErrors(['captcha' => $captchaError])->onlyInput('email');
        }
        $passwordMatches = $user
            && is_string($user->password)
            && password_verify($credentials['password'], $user->password);

        if ($user && $passwordMatches) {
            if ($user->trashed() || $user->status !== 'approved') {
                $message = $user->status === 'pending' ? 'Votre compte est en attente de validation.' : 'Votre compte est suspendu. Veuillez contacter l’administrateur.';

                return back()->withErrors(['email' => $message])->onlyInput('email');
            }

            if (Hash::needsRehash($user->password)) {
                $user->forceFill(['password' => $credentials['password']])->save();
            }

            Auth::login($user);
        }
        if (! $passwordMatches) {
            return back()->withErrors(['email' => 'Adresse e-mail ou mot de passe incorrect.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        $request->session()->put('account_session_version', (int) Auth::user()->session_version);

        return redirect(Auth::user()->getDashboardUrl());
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function forgot()
    {
        return view('auth.forgot-password');
    }

    public function sendReset(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        if ($captchaError = $this->verifyGoogleRecaptcha($request)) {
            return back()->withErrors(['captcha' => $captchaError])->onlyInput('email');
        }
        Password::sendResetLink(['email' => mb_strtolower(trim($request->email))]);

        return back()->with('status', 'Si cette adresse correspond à un compte, un lien de réinitialisation a été envoyé.');
    }

    public function resetForm(Request $request)
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate(['token' => ['required'], 'email' => ['required', 'email'], 'password' => ['required', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()->symbols()]]);
        $status = Password::reset($request->only('email', 'password', 'password_confirmation', 'token'), function (User $user, string $password) {
            $user->forceFill(['password' => $password, 'session_version' => $user->session_version + 1, 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });

        return $status === Password::PASSWORD_RESET ? redirect()->route('login')->with('status', __($status)) : back()->withErrors(['email' => __($status)]);
    }

    public function googleRole(Request $request)
    {
        $request->merge(['phone' => trim((string) $request->phone)]);
        $request->validate(['role' => ['required', 'in:'.implode(',', User::PUBLIC_ROLES)], 'phone' => ['required', 'regex:/^\d{8}$/', 'unique:users,phone'], 'terms' => ['accepted']]);
        if ($captchaError = $this->verifyGoogleRecaptcha($request)) {
            return back()->withErrors(['captcha' => $captchaError])->withInput($request->except('password'));
        }
        session(['google_signup_role' => $request->role, 'google_signup_phone' => '+216'.$request->phone, 'google_terms_accepted_at' => now()->toIso8601String()]);

        return redirect()->route('google.redirect');
    }

    public function googleRedirect()
    {
        abort_unless(config('services.google.client_id') && config('services.google.client_secret'), 503, 'Google OAuth n’est pas configuré.');

        return Socialite::driver('google')->with(['prompt' => 'select_account'])->redirect();
    }

    public function googleCallback()
    {
        try {
            $google = Socialite::driver('google')->user();
        } catch (\Throwable) {
            return redirect()->route('login')->withErrors(['email' => 'La connexion Google a échoué ou a été annulée.']);
        }
        if (! $google->getEmail() || ! filter_var($google->user['email_verified'] ?? $google->user['verified_email'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return redirect()->route('register')->withErrors(['email' => 'Google doit fournir une adresse vérifiée.']);
        }
        $user = User::where('google_id', $google->getId())->first();
        if ($user) {
            if (! $user->isApproved()) {
                return redirect()->route('login')->withErrors(['email' => 'Votre compte attend l’approbation ou est suspendu.']);
            }
            Auth::login($user);
            request()->session()->regenerate();

            return redirect($user->getDashboardUrl());
        }
        $existingUser = User::where('email', mb_strtolower($google->getEmail()))->first();
        if ($existingUser) {
            if (! $existingUser->isApproved()) {
                return redirect()->route('login')->withErrors(['email' => "Votre compte est en attente d'approbation ou suspendu."]);
            }

            if ($existingUser->google_id && ! hash_equals((string) $existingUser->google_id, (string) $google->getId())) {
                return redirect()->route('login')->withErrors(['email' => 'Ce compte est deja associe a un autre compte Google.']);
            }

            if (! $existingUser->google_id) {
                $existingUser->forceFill(['google_id' => $google->getId()])->save();
            }

            Auth::login($existingUser);
            request()->session()->regenerate();

            return redirect($existingUser->getDashboardUrl());
        }
        $role = session()->pull('google_signup_role');
        $phone = session()->pull('google_signup_phone');
        $termsAcceptedAt = session()->pull('google_terms_accepted_at');
        if (! in_array($role, User::PUBLIC_ROLES, true)) {
            return redirect()->route('register')->withErrors(['email' => 'Choisissez votre activité avant de continuer avec Google.']);
        }
        if (! $phone) {
            return redirect()->route('register')->withErrors(['email' => 'Recommencez la demande Google avec votre téléphone.']);
        }
        $newUser = User::create(['name' => $google->getName() ?: $google->getEmail(), 'email' => mb_strtolower($google->getEmail()), 'phone' => $phone, 'google_id' => $google->getId(), 'role' => $role, 'status' => 'pending', 'password' => Str::random(64), 'terms_accepted_at' => $termsAcceptedAt, 'terms_version' => '2026-10']);
        foreach (User::where('role', 'admin')->where('status', 'approved')->get() as $admin) {
            try {
                $admin->notify(new NewUserRegistration($newUser));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return redirect()->route('login')->with('status', 'Demande Google envoyée pour approbation.');
    }
}
