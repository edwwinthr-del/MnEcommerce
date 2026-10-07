<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Panel;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_rejects_privileged_fields_and_creates_only_customer(): void
    {
        $this->postJson('/api/v1/auth/register', [...$this->registration(), 'role' => 'admin'])
            ->assertUnprocessable()->assertJsonValidationErrors('role');

        $response = $this->postJson('/api/v1/auth/register', $this->registration())
            ->assertCreated()->assertJsonPath('data.role', 'customer')
            ->assertJsonMissingPath('data.password')->assertJsonMissingPath('data.remember_token');

        $user = User::firstOrFail();
        $this->assertTrue(Hash::check('a sufficiently long passphrase', $user->password));
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($response->getCookie(config('session.cookie'))->isHttpOnly());
    }

    public function test_short_and_overlong_byte_passwords_are_rejected(): void
    {
        foreach (['short', str_repeat('é', 40)] as $password) {
            $this->postJson('/api/v1/auth/register', [
                ...$this->registration(), 'password' => $password, 'password_confirmation' => $password,
            ])->assertUnprocessable()->assertJsonValidationErrors('password');
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_login_rotates_session_and_logout_invalidates_authentication(): void
    {
        $user = User::factory()->create(['password' => 'a sufficiently long passphrase']);
        $this->get('/sanctum/csrf-cookie')->assertNoContent();
        $oldSession = session()->getId();
        $this->postJson('/api/v1/auth/login', ['email' => strtoupper($user->email), 'password' => 'a sufficiently long passphrase'])
            ->assertOk()->assertJsonPath('data.id', $user->id);
        $this->assertNotSame($oldSession, session()->getId());
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.id', $user->id);
        $this->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->assertGuest('web');
        Auth::forgetGuards();
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_bad_login_is_generic_and_account_throttle_normalizes_case(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $attempt % 2 ? 'NOBODY@example.com' : 'nobody@example.com',
                'password' => 'incorrect password',
            ])->assertUnprocessable()->assertJsonValidationErrors('email');
        }

        $this->postJson('/api/v1/auth/login', ['email' => ' nobody@example.com ', 'password' => 'incorrect password'])
            ->assertTooManyRequests();
    }

    public function test_malformed_login_email_does_not_crash_throttle(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => ['unexpected'], 'password' => 'incorrect password'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_bearer_tokens_do_not_enable_an_unsupported_authentication_path(): void
    {
        $this->withHeader('Authorization', 'Bearer 1|fake-token')->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_customer_cannot_access_admin_panel_or_admin_gate(): void
    {
        $customer = User::factory()->create();
        $this->assertFalse($customer->canAccessPanel(Panel::make()->id('admin')));
        $this->assertFalse(Gate::forUser($customer)->allows('access-admin'));
        $this->actingAs($customer)->get('/admin')->assertForbidden();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->assertTrue($admin->canAccessPanel(Panel::make()->id('admin')));
        $this->assertTrue(Gate::forUser($admin)->allows('access-admin'));
    }

    public function test_customer_cannot_mass_assign_role(): void
    {
        $user = new User(['name' => 'Customer', 'email' => 'person@example.test', 'password' => 'a long password', 'role' => 'admin']);
        $this->assertNull($user->role);
    }

    public function test_password_reset_request_does_not_reveal_account_existence(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $known = $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk()->json();
        $unknown = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'missing@example.test'])->assertOk()->json();
        $this->assertSame($known, $unknown);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_tokens_are_single_use_and_invalid_tokens_do_not_change_password(): void
    {
        $user = User::factory()->create();
        $previousHash = $user->password;
        $payload = [
            'email' => $user->email, 'token' => 'invalid',
            'password' => 'a changed long passphrase', 'password_confirmation' => 'a changed long passphrase',
        ];
        $this->postJson('/api/v1/auth/reset-password', $payload)->assertUnprocessable();
        $this->assertSame($previousHash, $user->fresh()->password);
        $payload['token'] = Password::createToken($user);
        $this->postJson('/api/v1/auth/reset-password', $payload)->assertOk();
        $this->assertTrue(Hash::check($payload['password'], $user->fresh()->password));
        $this->postJson('/api/v1/auth/reset-password', $payload)->assertUnprocessable();
    }

    public function test_auth_writes_require_csrf_even_without_origin_header(): void
    {
        // Laravel bypasses CSRF in tests by default. Re-enable the actual guard here.
        $this->app->bind(PreventRequestForgery::class, EnforcedRequestForgeryProtection::class);
        $this->postJson('/api/v1/auth/register', $this->registration())->assertStatus(419);
        $this->withSession(['_token' => 'test-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'test-csrf-token')
            ->postJson('/api/v1/auth/register', $this->registration())->assertCreated();
    }

    public function test_successful_password_reset_also_invalidates_the_current_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email, 'token' => Password::createToken($user),
            'password' => 'a changed long passphrase', 'password_confirmation' => 'a changed long passphrase',
        ])->assertOk();
        $this->assertGuest('web');
    }

    public function test_password_reset_revokes_all_database_sessions_for_only_that_account(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create();
        $other = User::factory()->create();
        foreach (['device-one' => $user->id, 'device-two' => $user->id, 'other-user' => $other->id] as $id => $userId) {
            DB::table('sessions')->insert([
                'id' => $id, 'user_id' => $userId, 'payload' => '', 'last_activity' => now()->timestamp,
            ]);
        }

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email, 'token' => Password::createToken($user),
            'password' => 'a changed long passphrase', 'password_confirmation' => 'a changed long passphrase',
        ])->assertOk();

        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseHas('sessions', ['id' => 'other-user', 'user_id' => $other->id]);
    }

    public function test_stateful_checkout_posts_require_csrf(): void
    {
        config([
            'sanctum.stateful' => ['localhost'],
            'sanctum.middleware.validate_csrf_token' => EnforcedRequestForgeryProtection::class,
        ]);

        $this->withHeader('Origin', 'http://localhost')
            ->postJson('/api/v1/checkout/preview', ['items' => []])->assertStatus(419);
        $this->postJson('/api/v1/orders', ['items' => []])->assertStatus(419);

        $this->withSession(['_token' => 'checkout-csrf-token'])
            ->withHeader('X-CSRF-TOKEN', 'checkout-csrf-token')
            ->postJson('/api/v1/checkout/preview', ['items' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('items');
    }

    public function test_auth_responses_have_security_headers_and_are_not_cacheable(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');

        $this->actingAs(User::factory()->create())->getJson('/api/v1/auth/me')
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    private function registration(): array
    {
        return [
            'name' => 'Customer', 'email' => 'customer@example.test',
            'password' => 'a sufficiently long passphrase',
            'password_confirmation' => 'a sufficiently long passphrase',
        ];
    }
}

class EnforcedRequestForgeryProtection extends PreventRequestForgery
{
    protected function runningUnitTests(): bool
    {
        return false;
    }
}
