<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('سياق الفرع');
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
