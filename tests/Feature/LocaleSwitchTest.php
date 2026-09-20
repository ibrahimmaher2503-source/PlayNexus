<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LocaleSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_switch_locale_and_it_persists_on_the_login_page(): void
    {
        $this->post(route('locale.store'), ['locale' => 'ar'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('locale', 'ar');

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee('تسجيل دخول الموظفين');
    }

    public function test_guest_can_switch_locale_on_platform_login_and_stay_on_that_page(): void
    {
        $this->from(route('platform.login'))
            ->post(route('locale.store'), ['locale' => 'ar'])
            ->assertRedirect(route('platform.login'))
            ->assertSessionHas('locale', 'ar');

        $this->get(route('platform.login'))
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee('تسجيل الدخول إلى إدارة المنصة');
    }

    public function test_authenticated_user_returns_to_the_application_with_the_selected_locale(): void
    {
        $user = $this->staff();

        $this->actingAs($user)
            ->post(route('locale.store'), ['locale' => 'ar'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('locale', 'ar');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee(trans('actor_dashboard.titles.reception', [], 'ar'));
    }

    public function test_authenticated_user_stays_on_an_account_settings_page_when_switching_locale(): void
    {
        $user = $this->staff();
        $branch = $user->branches()->firstOrFail();

        $this->actingAs($user)
            ->from(route('branches.settings', $branch))
            ->post(route('locale.store'), ['locale' => 'ar'])
            ->assertRedirect(route('branches.settings', $branch))
            ->assertSessionHas('locale', 'ar');
    }

    public function test_platform_admin_stays_on_the_platform_dashboard_when_switching_locale(): void
    {
        $admin = User::factory()->create([
            'tenant_id' => null,
            'auth_version' => 1,
            'mfa_confirmed_at' => now(),
        ]);
        DB::table('platform_admins')->insert([
            'user_id' => $admin->id,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->withSession(['platform_auth_version' => 1, 'mfa_auth_version' => 1])
            ->from(route('platform.dashboard'))
            ->post(route('locale.store'), ['locale' => 'ar'])
            ->assertRedirect(route('platform.dashboard'))
            ->assertSessionHas('locale', 'ar');

        $this->get(route('platform.dashboard'))
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false);
    }

    public function test_locale_rejects_malformed_values_and_ignores_client_redirects(): void
    {
        $this->from(route('login'))
            ->post(route('locale.store'), ['locale' => 'fr', 'redirect' => 'https://example.test'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('locale')
            ->assertSessionMissing('locale');

        $this->from(route('login'))
            ->post(route('locale.store'), ['locale' => ['ar']])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('locale');
    }

    public function test_locale_select_submission_keeps_the_selected_value_enabled(): void
    {
        $script = file_get_contents(resource_path('js/app.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString('select.form?.requestSubmit();', $script);
        $this->assertStringNotContainsString('select.disabled = true;', $script);
    }

    public function test_login_validation_is_localized_after_switching_to_arabic(): void
    {
        $this->withSession(['locale' => 'ar'])
            ->from(route('login'))
            ->post(route('login.store'), ['email' => ['not-an-email'], 'password' => ''])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'email' => 'يجب أن يكون البريد الإلكتروني نصًا.',
                'password' => 'حقل كلمة المرور مطلوب.',
            ]);
    }

    public function test_generic_invalid_credentials_error_is_localized_in_arabic(): void
    {
        $this->withSession(['locale' => 'ar'])
            ->from(route('login'))
            ->post(route('login.store'), ['email' => 'missing@example.test', 'password' => 'wrong-password'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'بيانات الدخول غير صحيحة.'])
            ->assertSessionHas('locale', 'ar');

        $this->assertGuest();
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false);
    }

    private function staff(): User
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
        $user->branches()->attach($branch, [
            'tenant_id' => $tenant->id,
            'role' => 'reception',
            'is_active' => true,
        ]);

        return $user;
    }
}
