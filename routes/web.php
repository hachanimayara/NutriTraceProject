<?php

use App\Http\Controllers\Admin\ComplianceController;
use App\Http\Controllers\Admin\DocumentAnalysisController;
use App\Http\Controllers\Admin\ProductRatingController as AdminProductRatingController;
use App\Http\Controllers\Admin\TraceabilityAlertController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LocalRecaptchaDiagnosticController;
use App\Http\Controllers\MessagingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicCatalogController;
use App\Http\Controllers\TraceabilityDashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicCatalogController::class, 'home'])->name('front.home');
Route::get('/about', fn () => view('front.about'))->name('front.about');

// Keep the public entry points visible even when a session is already active.
// The form submissions remain guest-only below.
Route::get('/login', [AuthController::class, 'login'])->name('login');
Route::get('/register', [AuthController::class, 'register'])->name('register');

if (app()->environment('local')) {
    Route::get('/_local/diagnostics/recaptcha', LocalRecaptchaDiagnosticController::class)
        ->name('local.diagnostics.recaptcha');
}

Route::middleware('guest')->group(function () {
    Route::post('/login', [AuthController::class, 'authenticate'])->middleware('throttle:5,1')->name('login.store');
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
    foreach (['producteur', 'transformateur', 'distributeur'] as $role) {
        Route::get('/'.$role.'/dashboard', [TraceabilityDashboardController::class, 'index'])
            ->middleware('role:'.$role)->name($role.'.dashboard');
    }
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->middleware('throttle:5,1')->name('profile.password');
    Route::get('/notifications', function (Request $request) {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'read' => ['nullable', 'in:read,unread'],
        ]);
        $notifications = $request->user()->notifications()
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where('data->message', 'like', '%'.$term.'%'))
            ->when(($filters['read'] ?? null) === 'read', fn ($query) => $query->whereNotNull('read_at'))
            ->when(($filters['read'] ?? null) === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->latest()->paginate(5)->withQueryString();

        if ($request->boolean('popup')) {
            return view('profile.notifications-popup', compact('notifications'));
        }

        return view('profile.notifications', compact('notifications'));
    })->name('notifications.index');
    Route::post('/notifications/{id}/read', function (Request $r, string $id) {
        $r->user()->notifications()->whereKey($id)->update(['read_at' => now()]);

        return back();
    })->name('notifications.read');
    Route::delete('/notifications/{id}', function (Request $r, string $id) {
        $r->user()->notifications()->whereKey($id)->delete();

        return back();
    })->name('notifications.destroy');
    Route::get('/user/confirm-password', fn () => view('auth.confirm-password'))->name('password.confirm');
    Route::post('/user/confirm-password', function (Request $r) {
        $r->validate(['password' => ['required', 'current_password']]);
        $r->session()->passwordConfirmed();

        return redirect()->intended(route('profile.edit'));
    })->name('password.confirm.store');
});

Route::middleware(['auth', 'approved', 'role:transformateur,distributeur'])->prefix('messaging')->name('messaging.')->group(function () {
    Route::get('/', [MessagingController::class, 'index'])->name('index');
    Route::get('/state', [MessagingController::class, 'state'])->middleware('throttle:30,1')->name('state');
    Route::post('/presence', [MessagingController::class, 'heartbeat'])->middleware('throttle:6,1')->name('presence');
    Route::post('/conversations', [MessagingController::class, 'createConversation'])->middleware('throttle:10,1')->name('conversations.store');
    Route::get('/conversations/{conversation}/messages', [MessagingController::class, 'messages'])->whereNumber('conversation')->middleware('throttle:60,1')->name('messages.index');
    Route::post('/conversations/{conversation}/messages', [MessagingController::class, 'send'])->whereNumber('conversation')->middleware('throttle:30,1')->name('messages.store');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'approved', 'role:admin'])->group(function () {
    Route::get('/', fn () => redirect()->route('admin.dashboard'));
    Route::get('/dashboard', [TraceabilityDashboardController::class, 'index'])->name('dashboard');
    Route::get('/compliance', [ComplianceController::class, 'dashboard'])->name('compliance.dashboard');
    Route::post('/compliance/analyze', [ComplianceController::class, 'analyze'])->middleware('throttle:6,1')->name('compliance.analyze');
    Route::get('/compliance/certifications', [ComplianceController::class, 'certifications'])->name('compliance.certifications');
    Route::get('/compliance/alerts', [ComplianceController::class, 'alerts'])->name('compliance.alerts');
    Route::get('/compliance/alerts/{alert}', [ComplianceController::class, 'showAlert'])->whereNumber('alert')->name('compliance.alerts.show');
    Route::patch('/compliance/alerts/{alert}/decision', [ComplianceController::class, 'decide'])->whereNumber('alert')->name('compliance.alerts.decision');
    Route::post('/compliance/alerts/{alert}/actions', [ComplianceController::class, 'createAction'])->whereNumber('alert')->name('compliance.alerts.actions.store');
    Route::get('/compliance/actions', [ComplianceController::class, 'actions'])->name('compliance.actions');
    Route::patch('/compliance/actions/{action}', [ComplianceController::class, 'updateAction'])->whereNumber('action')->name('compliance.actions.update');
    Route::get('/compliance/actions/{action}/evidence', [ComplianceController::class, 'actionEvidence'])->whereNumber('action')->name('compliance.actions.evidence');
    Route::get('/alerts', [TraceabilityAlertController::class, 'index'])->name('alerts.index');
    Route::post('/alerts/analyze', [TraceabilityAlertController::class, 'analyze'])->name('alerts.analyze');
    Route::post('/alerts/{alert}/resolve', [TraceabilityAlertController::class, 'resolve'])->name('alerts.resolve');
    Route::get('/document-analyses', [DocumentAnalysisController::class, 'index'])->name('document-analyses.index');
    Route::post('/document-analyses', [DocumentAnalysisController::class, 'store'])->middleware('throttle:10,1')->name('document-analyses.store');
    Route::get('/document-analyses/{analysis}', [DocumentAnalysisController::class, 'show'])->name('document-analyses.show');
    Route::post('/document-analyses/{analysis}/analyze', [DocumentAnalysisController::class, 'analyze'])->middleware('throttle:10,1')->name('document-analyses.analyze');
    Route::get('/document-analyses/{analysis}/file', [DocumentAnalysisController::class, 'download'])->name('document-analyses.file');
    Route::put('/document-analyses/{analysis}/review', [DocumentAnalysisController::class, 'review'])->name('document-analyses.review');
    Route::delete('/document-analyses/{analysis}', [DocumentAnalysisController::class, 'destroy'])->name('document-analyses.destroy');
    Route::get('/product-ratings', [AdminProductRatingController::class, 'index'])->name('product-ratings.index');
    Route::get('/product-ratings/{productRating}', [AdminProductRatingController::class, 'show'])->whereNumber('productRating')->name('product-ratings.show');
    Route::patch('/product-ratings/{productRating}/publication', [AdminProductRatingController::class, 'togglePublication'])->whereNumber('productRating')->name('product-ratings.publication');
    Route::get('/users/export', [AdminUserController::class, 'export'])->name('users.export');
    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}/role-document', [AdminUserController::class, 'roleDocument'])->name('users.role-document');
    Route::get('/users/{user}', [AdminUserController::class, 'show'])->withTrashed()->name('users.show');
    Route::post('/users/{user}/approve', [AdminUserController::class, 'approve'])->name('users.approve');
    Route::post('/users/{user}/activate', [AdminUserController::class, 'activate'])->name('users.activate');
    Route::post('/users/{user}/suspend', [AdminUserController::class, 'suspend'])->name('users.suspend');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
});

require __DIR__.'/traceability.php';
