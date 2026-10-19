<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AccountApproved;
use App\Notifications\NewUserRegistration;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;
use Tests\TestCase;

class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google_recaptcha.enabled' => true, 'services.google_recaptcha.site_key' => 'test-site-key', 'services.google_recaptcha.secret' => 'test-secret']);
        Http::fake(fn ($request) => Http::response(['success' => $request['response'] !== 'bad-token'], 200));
    }

    public function test_public_signup_creates_pending_user_and_notifies_admin(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin', 'is_protected_admin' => true]);
        $response = $this->post(route('register.store'), [
            'name' => 'Élodie Ben Salem', 'email' => '  Test.User@example.com ', 'phone' => '12345678',
            'role' => 'producteur', 'password' => 'SecurePass!1234', 'password_confirmation' => 'SecurePass!1234', 'terms' => '1', 'g-recaptcha-response' => 'test-token',
        ]);
        $response->assertRedirect(route('login'));
        $user = User::where('email', 'test.user@example.com')->firstOrFail();
        $this->assertSame('pending', $user->status);
        $this->assertSame('producteur', $user->role);
        $this->assertSame('+21612345678', $user->phone);
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertNotSame('SecurePass!1234', $user->password);
        Notification::assertSentTo($admin, NewUserRegistration::class);
    }

    public function test_public_registration_cannot_assign_admin_and_validates_phone_and_password(): void
    {
        $response = $this->from('/register')->post(route('register.store'), [
            'name' => 'Test Person', 'email' => 'test@example.com', 'phone' => '123', 'role' => 'admin',
            'password' => 'weak', 'password_confirmation' => 'different', 'terms' => '1', 'g-recaptcha-response' => 'test-token',
        ]);
        $response->assertRedirect('/register')->assertSessionHasErrors(['role', 'phone', 'password']);
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_every_public_role_can_register_with_strict_eight_digit_phone(): void
    {
        foreach (User::PUBLIC_ROLES as $index => $role) {
            $this->travel(61)->seconds();
            $email = $role.'@example.test';
            $response = $this->post(route('register.store'), [
                'name' => 'Test Person', 'email' => $email, 'phone' => '1234567x', 'role' => $role,
                'password' => 'SecurePass!1234', 'password_confirmation' => 'SecurePass!1234', 'terms' => '1', 'g-recaptcha-response' => 'test-token',
            ]);
            $response->assertSessionHasErrors('phone');
            $this->assertDatabaseMissing('users', ['email' => $email]);
            $response = $this->post(route('register.store'), [
                'name' => 'Test Person', 'email' => $email, 'phone' => (string) (12345678 + $index), 'role' => $role,
                'password' => 'SecurePass!1234', 'password_confirmation' => 'SecurePass!1234', 'terms' => '1', 'g-recaptcha-response' => 'test-token',
            ]);
            $response->assertRedirect(route('login'));
            $this->assertDatabaseHas('users', ['email' => $email, 'role' => $role, 'status' => 'pending']);
        }
    }

    public function test_pending_user_cannot_login_and_role_dashboard_is_protected(): void
    {
        $pending = User::factory()->create(['email' => 'pending@example.test', 'password' => 'SecurePass!1234', 'status' => 'pending']);
        $this->post(route('login.store'), ['email' => $pending->email, 'password' => 'SecurePass!1234', 'g-recaptcha-response' => 'test-token'])->assertSessionHasErrors('email');
        $suspended = User::factory()->create(['email' => 'suspended@example.test', 'status' => 'suspended']);
        $this->post(route('login.store'), ['email' => $suspended->email, 'password' => 'password', 'g-recaptcha-response' => 'test-token'])->assertSessionHasErrors('email');
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_login_redirects_approved_user_to_their_saved_role(): void
    {
        $producer = User::factory()->create(['role' => 'producteur', 'email' => 'producer@example.test']);
        $this->post(route('login.store'), ['email' => $producer->email, 'password' => 'password', 'g-recaptcha-response' => 'test-token'])
            ->assertRedirect(route('producteur.dashboard'));
        $this->assertAuthenticatedAs($producer);
    }

    public function test_old_account_session_version_is_rejected(): void
    {
        $user = User::factory()->create(['session_version' => 2]);
        $this->actingAs($user)->withSession(['account_session_version' => 1])->get(route('profile.edit'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_authentication_pages_render_and_admin_can_open_user_management(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Connexion');
        $this->get(route('register'))->assertOk()->assertSee('Créer un compte');
        $this->get(route('password.request'))->assertOk()->assertSee('Mot de passe oublié');
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->assertSee('Gestion des utilisateurs');
        $this->get(route('profile.edit'))->assertOk()->assertSee('Passkeys');
    }

    public function test_signup_persists_an_admin_database_notification(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->post(route('register.store'), [
            'name' => 'Trace User', 'email' => 'trace@example.test', 'phone' => '22334455', 'role' => 'distributeur',
            'password' => 'SecurePass!1234', 'password_confirmation' => 'SecurePass!1234', 'terms' => '1', 'g-recaptcha-response' => 'test-token',
        ])->assertRedirect(route('login'));
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $admin->id, 'type' => NewUserRegistration::class]);
    }

    public function test_suspending_an_account_marks_it_suspended_and_increments_session_version(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['session_version' => 0]);
        $this->actingAs($admin)->post(route('admin.users.suspend', $user))->assertSessionHas('status');
        $this->assertSame('suspended', $user->fresh()->status);
        $this->assertSame(1, $user->fresh()->session_version);
    }

    public function test_approved_user_can_only_access_their_role_dashboard(): void
    {
        $producer = User::factory()->create(['role' => 'producteur']);
        $this->actingAs($producer)->get(route('producteur.dashboard'))->assertOk();
        $this->actingAs($producer)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_profile_shows_passkey_configuration_status_and_controls(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Non configurée')
            ->assertSee('Configurer Face ID')
            ->assertSee('consens');

        $user->passkeys()->create([
            'name' => 'Téléphone de test',
            'credential_id' => 'credential-profile-test',
            'credential' => [],
        ]);

        $this->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Configurée')
            ->assertSee('Modifier mon visage')
            ->assertSee('Désactiver la reconnaissance faciale');
    }

    public function test_disabling_passkeys_deletes_only_the_authenticated_users_credentials(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $user->passkeys()->create(['name' => 'Téléphone', 'credential_id' => 'credential-own', 'credential' => []]);
        $otherUser->passkeys()->create(['name' => 'Autre téléphone', 'credential_id' => 'credential-other', 'credential' => []]);

        $this->actingAs($user)->delete(route('profile.passkeys.disable'))
            ->assertRedirect()
            ->assertSessionHas('status', 'La connexion biométrique a été désactivée.');

        $this->assertDatabaseMissing('passkeys', ['user_id' => $user->id]);
        $this->assertDatabaseHas('passkeys', ['user_id' => $otherUser->id, 'credential_id' => 'credential-other']);
    }

    public function test_passkey_consent_must_be_explicit_and_is_recorded(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('profile.passkeys.consent'), ['consent' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('consent');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'passkey_consent_at' => null]);

        $this->postJson(route('profile.passkeys.consent'), ['consent' => true])
            ->assertOk()
            ->assertJson(['status' => 'consent-recorded']);
        $this->assertNotNull($user->fresh()->passkey_consent_at);
    }

    public function test_passkey_login_requires_approved_non_deleted_account(): void
    {
        $pending = User::factory()->create(['status' => 'pending']);
        $passkey = new Passkey;
        $passkey->setRelation('user', $pending);

        $this->assertFalse(Passkeys::allowsLogin(request(), $passkey));

        $approved = User::factory()->create(['status' => 'approved']);
        $passkey->setRelation('user', $approved);
        $this->assertTrue(Passkeys::allowsLogin(request(), $passkey));

        $deleted = User::factory()->create(['status' => 'approved']);
        $deleted->delete();
        $passkey->setRelation('user', $deleted);
        $this->assertFalse(Passkeys::allowsLogin(request(), $passkey));
    }

    public function test_login_offers_discoverable_passkey_without_revealing_account_registration(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Connexion biométrique (Face ID / appareil)')
            ->assertSee('NutriTrace ne reçoit aucune donnée biométrique.');

        $this->assertFalse(Route::has('passkeys.available'));
    }

    public function test_admin_can_approve_pending_user_and_protected_admin_cannot_be_deleted(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin', 'is_protected_admin' => true]);
        $pending = User::factory()->create(['status' => 'pending']);
        $this->actingAs($admin)->post(route('admin.users.approve', $pending))->assertSessionHas('status');
        $this->assertSame('approved', $pending->fresh()->status);
        Notification::assertSentTo($pending, AccountApproved::class);
        $approvalMail = (new AccountApproved)->toMail($pending);
        $this->assertSame('Se connecter', $approvalMail->actionText);
        $this->assertSame(route('login'), $approvalMail->actionUrl);
        $this->actingAs($admin)->delete(route('admin.users.destroy', $admin))->assertUnprocessable();
    }

    public function test_non_admin_cannot_approve_accounts(): void
    {
        $user = User::factory()->create();
        $pending = User::factory()->create(['status' => 'pending']);
        $this->actingAs($user)->post(route('admin.users.approve', $pending))->assertForbidden();
        $this->assertSame('pending', $pending->fresh()->status);
    }

    public function test_invalid_recaptcha_token_is_rejected_by_server(): void
    {
        $this->from('/register')->post(route('register.store'), [
            'name' => 'Test Person', 'email' => 'captcha@example.test', 'phone' => '11223344', 'role' => 'consommateur',
            'password' => 'SecurePass!1234', 'password_confirmation' => 'SecurePass!1234', 'terms' => '1', 'g-recaptcha-response' => 'bad-token',
        ])->assertSessionHasErrors('captcha');
        $this->assertDatabaseMissing('users', ['email' => 'captcha@example.test']);
    }

    public function test_signup_without_recaptcha_token_is_rejected_without_calling_google(): void
    {
        $this->from('/register')->post(route('register.store'), [
            'name' => 'Test Person', 'email' => 'missing-captcha@example.test', 'phone' => '11223344', 'role' => 'consommateur',
            'password' => 'SecurePass!1234', 'password_confirmation' => 'SecurePass!1234', 'terms' => '1',
        ])->assertSessionHasErrors('captcha');

        Http::assertNothingSent();
        $this->assertDatabaseMissing('users', ['email' => 'missing-captcha@example.test']);
    }

    public function test_login_without_recaptcha_token_does_not_authenticate(): void
    {
        $user = User::factory()->create(['status' => 'approved']);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('captcha');

        Http::assertNothingSent();
        $this->assertGuest();
    }

    public function test_login_with_rejected_recaptcha_token_does_not_authenticate(): void
    {
        $user = User::factory()->create(['status' => 'approved']);
        $this->post(route('login.store'), [
            'email' => $user->email, 'password' => 'password', 'g-recaptcha-response' => 'bad-token',
        ])->assertSessionHasErrors('captcha');

        $this->assertGuest();
    }

    public function test_signup_is_rejected_when_google_is_unreachable(): void
    {
        Log::spy();
        Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect to www.google.com port 443: Couldn\'t connect to server for https://www.google.com/recaptcha/api/siteverify?token=captcha-token-123&email=private@example.test'));
        $this->from('/register')->post(route('register.store'), [
            'name' => 'Test Person', 'email' => 'network-captcha@example.test', 'phone' => '11223344', 'role' => 'consommateur',
            'password' => 'SecurePass!1234', 'password_confirmation' => 'SecurePass!1234', 'terms' => '1', 'g-recaptcha-response' => 'captcha-token-123',
        ])->assertSessionHasErrors('captcha');

        $this->assertDatabaseMissing('users', ['email' => 'network-captcha@example.test']);
        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context): bool {
            $technicalMessage = $context['technical_message'] ?? '';

            return $message === 'Google reCAPTCHA verification request failed.'
                && ($context['failure_class'] ?? null) === 'connection_refused_or_unreachable'
                && ($context['curl_code'] ?? null) === 7
                && array_key_exists('http_status', $context) && $context['http_status'] === null
                && ($context['google_error_codes'] ?? null) === []
                && ($context['captcha_token_present'] ?? false) === true
                && str_contains($technicalMessage, 'cURL error 7')
                && ! str_contains($technicalMessage, 'captcha-token-123')
                && ! str_contains($technicalMessage, 'test-secret')
                && ! str_contains($technicalMessage, 'private@example.test')
                && ! str_contains($technicalMessage, 'network-captcha@example.test');
        });
    }

    public function test_profile_updates_cannot_change_role_and_password_requires_current_password(): void
    {
        $user = User::factory()->create(['role' => 'producteur', 'phone' => '+21612345678']);
        $this->actingAs($user)->put(route('profile.update'), ['name' => 'Updated Name', 'email' => $user->email, 'phone' => '87654321', 'role' => 'admin'])->assertRedirect();
        $this->assertSame('producteur', $user->fresh()->role);
        $this->assertSame('+21687654321', $user->fresh()->phone);
        $this->actingAs($user)->put(route('profile.password'), ['current_password' => 'wrong', 'password' => 'NewSecurePass!123', 'password_confirmation' => 'NewSecurePass!123'])->assertSessionHasErrors('current_password');
        $this->actingAs($user)->put(route('profile.password'), ['current_password' => 'password', 'password' => 'NewSecurePass!123', 'password_confirmation' => 'NewSecurePass!123'])->assertSessionHas('status');
        $this->assertTrue(Hash::check('NewSecurePass!123', $user->fresh()->password));
    }

    public function test_password_reset_uses_one_time_token_and_revokes_existing_sessions(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post(route('password.email'), ['email' => $user->email, 'g-recaptcha-response' => 'test-token'])->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPasswordNotification::class);
        $token = app('auth.password.broker')->createToken($user);
        $version = $user->session_version;
        $this->post(route('password.update'), ['token' => $token, 'email' => $user->email, 'password' => 'ResetSecure!1234', 'password_confirmation' => 'ResetSecure!1234'])->assertRedirect(route('login'));
        $this->assertSame($version + 1, $user->fresh()->session_version);
        $this->assertFalse(app('auth.password.broker')->tokenExists($user, $token));
    }
}
