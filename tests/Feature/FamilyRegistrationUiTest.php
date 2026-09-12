<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\Guardian;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FamilyRegistrationUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_english_index_shows_current_family_children_and_masked_phone(): void
    {
        [$tenant, $owner] = $this->owner();
        $guardian = Guardian::factory()->create([
            'tenant_id' => $tenant->id,
            'full_name' => 'Ahmed Ali',
            'phone_e164' => '+201000000000',
        ]);
        $child = Child::factory()->create([
            'tenant_id' => $tenant->id,
            'full_name' => 'Maya Ahmed',
        ]);
        $guardian->children()->attach($child, [
            'tenant_id' => $tenant->id,
            'relationship_type' => 'parent',
            'is_active' => true,
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get(route('families.index', ['q' => '+201000000000']))
            ->assertOk()
            ->assertViewIs('families.index')
            ->assertSee(__('families.page_title'))
            ->assertSee($guardian->full_name)
            ->assertSee($child->full_name)
            ->assertSee($guardian->maskedPhone())
            ->assertSee('name="q"', false)
            ->assertSee(__('families.add_link'));

        $this->assertNotSame($guardian->phone_e164, $guardian->maskedPhone());
    }

    public function test_arabic_index_keeps_rtl_shell_and_no_match_recovery(): void
    {
        [$tenant, $owner] = $this->owner();

        $this->actingAs($owner)
            ->withSession(['locale' => 'ar'])
            ->get(route('families.index', ['q' => 'لا توجد أسرة']))
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee(__('families.no_matches_heading', [], 'ar'))
            ->assertSee(__('families.no_matches_description', [], 'ar'))
            ->assertSee(__('families.add_link', [], 'ar'))
            ->assertSee($tenant->name);
    }

    public function test_create_form_is_bilingual_accessible_and_contains_only_the_bounded_fields(): void
    {
        [, $owner] = $this->owner();

        $this->actingAs($owner)
            ->get(route('families.create'))
            ->assertOk()
            ->assertViewIs('families.create')
            ->assertSee('<form', false)
            ->assertSee('<fieldset', false)
            ->assertSee('for="guardian-name"', false)
            ->assertSee('name="guardian_name"', false)
            ->assertSee('for="guardian-phone"', false)
            ->assertSee('name="phone"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="preferred_locale"', false)
            ->assertSee('name="child_name"', false)
            ->assertSee('name="date_of_birth"', false)
            ->assertSee('name="relationship_type"', false)
            ->assertSee('value="parent"', false)
            ->assertSee('value="mother"', false)
            ->assertSee('value="father"', false)
            ->assertSee('value="other"', false)
            ->assertSee('min-h-11', false)
            ->assertSee(__('families.operational_notice'))
            ->assertDontSee('name="consent"', false)
            ->assertDontSee('name="notes"', false);

        $this->actingAs($owner)
            ->withSession(['locale' => 'ar'])
            ->get(route('families.create'))
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee(__('families.guardian_section', [], 'ar'))
            ->assertSee(__('families.child_section', [], 'ar'));

        $this->actingAs($owner)
            ->get(route('families.create', ['q' => '0100 111 2233']))
            ->assertSee('value="0100 111 2233"', false);

        $this->actingAs($owner)
            ->get(route('families.create', ['q' => 'Maya Ahmed']))
            ->assertDontSee('value="Maya Ahmed"', false);
    }

    public function test_index_keeps_success_and_validation_feedback_visible(): void
    {
        [, $owner] = $this->owner();

        $this->actingAs($owner)
            ->withSession(['success' => __('families.created')])
            ->get(route('families.index'))
            ->assertSee(__('families.created'))
            ->assertSee('role="status"', false)
            ->assertSee('aria-live="polite"', false);

        $this->actingAs($owner)
            ->from(route('families.create'))
            ->post(route('families.store'), ['phone' => 'not-a-phone'])
            ->assertRedirect(route('families.create'))
            ->assertSessionHasErrors('phone');
    }

    /** @return array{Tenant, User} */
    private function owner(): array
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['tenant_id' => $tenant->id]);
        DB::table('tenant_owners')->insert([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$tenant, $owner];
    }
}
