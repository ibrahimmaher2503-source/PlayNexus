<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_form_is_accessible_in_english_and_arabic(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSee('name="email"', false)
            ->assertSee(__('passwords.send_link'));

        $this->withSession(['locale' => 'ar'])
            ->get(route('password.request'))
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee(trans('passwords.send_link', [], 'ar'));
    }

    public function test_known_and_unknown_email_requests_have_the_same_generic_response(): void
    {
        Notification::fake();
        [, $user] = $this->staff();

        $known = $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => ' '.$user->email.' ']);
        $unknown = $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'unknown@example.test']);

        $known->assertRedirect(route('password.request'))
            ->assertSessionHas('status', __('passwords.sent'));
        $unknown->assertRedirect(route('password.request'))
            ->assertSessionHas('status', __('passwords.sent'));
        $this->assertSame($known->getStatusCode(), $unknown->getStatusCode());
        Notification::assertSentTo($user, ResetPassword::class);
        $this->assertDatabaseCount('password_reset_tokens', 1);
    }

    public function test_ineligible_accounts_receive_the_same_generic_response_without_a_token_or_notification(): void
    {
        Notification::fake();

        foreach (['invited', 'suspended', 'disabled'] as $status) {
            [, $user] = $this->staff($status);
            $this->from(route('password.request'))
                ->post(route('password.email'), ['email' => $user->email])
                ->assertRedirect(route('password.request'))
                ->assertSessionHas('status', __('passwords.sent'));
        }

        [$tenant, $user] = $this->staff();
        $tenant->update(['is_active' => false]);
        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', __('passwords.sent'));

        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_password_broker_throttle_does_not_create_a_second_token(): void
    {
        Notification::fake();
        [, $user] = $this->staff();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status', __('passwords.sent'));
        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status', __('passwords.sent'));

        $this->assertDatabaseCount('password_reset_tokens', 1);
    }

    public function test_valid_reset_is_single_use_updates_credentials_and_revokes_an_existing_session(): void
    {
        Notification::fake();
        [, $user] = $this->staff();
        $oldRememberToken = $user->remember_token;

        $this->post(route('password.email'), ['email' => $user->email]);
        $token = $this->resetToken($user);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));
        $this->assertSame(1, session('auth_version'));
        Auth::logout();

        $this->from(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->post(route('password.update'), [
                'token' => $token,
                'email' => ' '.$user->email.' ',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', __('passwords.reset'));

        $user->refresh();
        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertFalse(Hash::check('password', $user->password));
        $this->assertNotSame($oldRememberToken, $user->remember_token);
        $this->assertSame(2, (int) $user->auth_version);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));
        $this->assertGuest();

        $this->from(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->post(route('password.update'), [
                'token' => $token,
                'email' => $user->email,
                'password' => 'another-password',
                'password_confirmation' => 'another-password',
            ])
            ->assertRedirect(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }

    public function test_tampered_and_expired_tokens_do_not_change_the_password(): void
    {
        Notification::fake();
        [, $tamperedUser] = $this->staff();
        $this->from(route('password.reset', ['token' => 'tampered', 'email' => $tamperedUser->email]))
            ->post(route('password.update'), [
                'token' => 'tampered',
                'email' => $tamperedUser->email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertRedirect(route('password.reset', ['token' => 'tampered', 'email' => $tamperedUser->email]))
            ->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('password', $tamperedUser->refresh()->password));

        [, $expiredUser] = $this->staff();
        $this->post(route('password.email'), ['email' => $expiredUser->email]);
        $token = $this->resetToken($expiredUser);
        DB::table('password_reset_tokens')
            ->where('email', $expiredUser->email)
            ->update(['created_at' => now()->subMinutes((int) config('auth.passwords.users.expire') + 1)]);

        $this->from(route('password.reset', ['token' => $token, 'email' => $expiredUser->email]))
            ->post(route('password.update'), [
                'token' => $token,
                'email' => $expiredUser->email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertRedirect(route('password.reset', ['token' => $token, 'email' => $expiredUser->email]))
            ->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('password', $expiredUser->refresh()->password));
    }

    public function test_login_stores_the_current_auth_version(): void
    {
        [, $user] = $this->staff();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertSame(1, session('auth_version'));
    }

    /** @return array{Tenant, User} */
    private function staff(string $status = 'active'): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => Hash::make('password'),
            'status' => $status,
        ]);

        return [$tenant, $user];
    }

    private function resetToken(User $user): string
    {
        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->assertIsString($token);
        $this->assertNotSame('', $token);

        return $token;
    }
}
