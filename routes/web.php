<?php

use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('front.home'))->name('front.home');
Route::get('/about', fn () => view('front.about'))->name('front.about');

if (app()->environment('local')) {
    Route::get('/_local/diagnostics/recaptcha', App\Http\Controllers\LocalRecaptchaDiagnosticController::class)
        ->name('local.diagnostics.recaptcha');
}

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/login', [AuthController::class, 'authenticate'])->middleware('throttle:5,1')->name('login.store');
    Route::get('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/register', [AuthController::class, 'storeRegistration'])->middleware('throttle:3,1')->name('register.store');
    Route::get('/forgot-password', [AuthController::class, 'forgot'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendReset'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
    Route::post('/auth/google/role', [AuthController::class, 'googleRole'])->name('google.role');
    Route::get('/auth/google', [AuthController::class, 'googleRedirect'])->name('google.redirect');
    Route::get('/auth/google/callback', [AuthController::class, 'googleCallback'])->name('google.callback');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'approved'])->group(function () {
    Route::get('/dashboard', fn (Request $r) => redirect($r->user()->getDashboardUrl()))->name('dashboard');
    foreach (['producteur', 'transformateur', 'distributeur', 'consommateur'] as $role) {
        Route::get('/'.$role.'/dashboard', fn (Request $r) => view('dashboard.role', ['role' => $role]))
            ->middleware('role:'.$role)->name($role.'.dashboard');
    }
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->middleware('throttle:5,1')->name('profile.password');
    Route::post('/profile/passkeys/consent', [ProfileController::class, 'consentPasskeys'])->middleware('throttle:3,1')->name('profile.passkeys.consent');
    Route::delete('/profile/passkeys', [ProfileController::class, 'disablePasskeys'])->middleware('throttle:3,1')->name('profile.passkeys.disable');
    Route::get('/notifications', fn (Request $r) => view('profile.notifications', ['notifications' => $r->user()->notifications()->latest()->paginate(20)]))->name('notifications.index');
    Route::post('/notifications/{id}/read', function (Request $r, string $id) {
        $r->user()->notifications()->whereKey($id)->update(['read_at' => now()]);

        return back();
    })->name('notifications.read');
    Route::get('/user/confirm-password', fn () => view('auth.confirm-password'))->name('password.confirm');
    Route::post('/user/confirm-password', function (Request $r) {
        $r->validate(['password' => ['required', 'current_password']]);
        $r->session()->passwordConfirmed();

        return redirect()->intended(route('profile.edit'));
    })->name('password.confirm.store');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'approved', 'role:admin'])->group(function () {
    Route::get('/', fn () => redirect()->route('admin.dashboard'));
    Route::get('/dashboard', fn () => view('dashboard.role', ['role' => 'admin']))->name('dashboard');
    Route::get('/users/export', [AdminUserController::class, 'export'])->name('users.export');
    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [AdminUserController::class, 'show'])->withTrashed()->name('users.show');
    Route::post('/users/{user}/approve', [AdminUserController::class, 'approve'])->name('users.approve');
    Route::post('/users/{user}/activate', [AdminUserController::class, 'activate'])->name('users.activate');
    Route::post('/users/{user}/suspend', [AdminUserController::class, 'suspend'])->name('users.suspend');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
});
