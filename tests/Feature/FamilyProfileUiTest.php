<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\Guardian;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FamilyProfileUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_shows_masked_phone_children_and_scoped_edit_forms(): void
    {
        [$tenant, $owner] = $this->owner();
        $guardian = Guardian::factory()->create([
            'tenant_id' => $tenant->id,
            'full_name' => 'Ahmed Ali',
            'phone_e164' => '+201000000000',
            'email' => 'ahmed@example.test',
            'preferred_locale' => 'en',
            'lock_version' => 3,
        ]);
        $child = Child::factory()->create([
            'tenant_id' => $tenant->id,
            'full_name' => 'Maya Ahmed',
            'date_of_birth' => '2020-05-06',
            'lock_version' => 4,
        ]);
        $this->link($tenant, $owner, $guardian, $child, 'mother');

        $response = $this->actingAs($owner)
            ->get(route('families.show', $guardian))
            ->assertOk()
            ->assertViewIs('families.show')
            ->assertSee($guardian->full_name)
            ->assertSee($child->full_name)
            ->assertSee($guardian->maskedPhone())
            ->assertSee(__('families.profile_title'))
            ->assertSee(__('families.edit_guardian_heading'))
            ->assertSee(__('families.children_heading'))
            ->assertSee(__('families.add_child_heading'))
            ->assertSee('name="expected_version"', false)
            ->assertSee('name="guardian_name"', false)
            ->assertSee('name="phone"', false)
            ->assertSee('name="child_name"', false)
            ->assertSee('name="date_of_birth"', false)
            ->assertSee('name="relationship_type"', false);

        $html = $response->getContent();
        $this->assertNotFalse($html);
        $this->assertStringContainsString(route('families.update', $guardian), $html);
        $this->assertStringContainsString(route('families.children.update', [$guardian, $child]), $html);
        $this->assertStringContainsString(route('families.children.store', $guardian), $html);
        $this->assertStringContainsString('value="mother"', $html);
        $this->assertStringNotContainsString('name="consent"', $html);
        $this->assertStringNotContainsString('name="safety', $html);
        $this->assertStringNotContainsString('name="emergency', $html);
    }

    public function test_arabic_profile_is_rtl_and_keeps_relationship_choices_localized(): void
    {
        [$tenant, $owner] = $this->owner();
        $guardian = Guardian::factory()->create([
            'tenant_id' => $tenant->id,
            'full_name' => 'أحمد علي',
            'phone_e164' => '+201000000000',
            'preferred_locale' => 'ar',
        ]);
        $child = Child::factory()->create(['tenant_id' => $tenant->id, 'full_name' => 'مايا أحمد']);
        $this->link($tenant, $owner, $guardian, $child, 'mother');

        $this->actingAs($owner)
            ->withSession(['locale' => 'ar'])
            ->get(route('families.show', $guardian))
            ->assertOk()
            ->assertSee('lang="ar"', false)
            ->assertSee('dir="rtl"', false)
            ->assertSee(__('families.profile_title', [], 'ar'))
            ->assertSee(__('families.edit_guardian_heading', [], 'ar'))
            ->assertSee(__('families.relationship_types.mother', [], 'ar'))
            ->assertSee(__('families.add_child', [], 'ar'));
    }

    public function test_profile_keeps_success_and_validation_feedback_visible(): void
    {
        [$tenant, $owner] = $this->owner();
        $guardian = Guardian::factory()->create(['tenant_id' => $tenant->id, 'phone_e164' => '+201000000000']);

        $this->actingAs($owner)
            ->withSession(['success' => __('families.updated')])
            ->get(route('families.show', $guardian))
            ->assertSee(__('families.updated'))
            ->assertSee('role="status"', false)
            ->assertSee('aria-live="polite"', false);

        $this->actingAs($owner)
            ->withSession(['conflict' => __('families.conflict')])
            ->get(route('families.show', $guardian))
            ->assertSee(__('families.conflict_title'))
            ->assertSee(__('families.refresh_profile'))
            ->assertSee('role="alert"', false)
            ->assertSee('aria-live="assertive"', false);
    }

    public function test_failed_child_form_does_not_fill_sibling_or_add_child_forms(): void
    {
        [$tenant, $owner] = $this->owner();
        $guardian = Guardian::factory()->create(['tenant_id' => $tenant->id]);
        $first = Child::factory()->create(['tenant_id' => $tenant->id, 'full_name' => 'First child']);
        $second = Child::factory()->create(['tenant_id' => $tenant->id, 'full_name' => 'Second child']);
        $this->link($tenant, $owner, $guardian, $first, 'mother');
        $this->link($tenant, $owner, $guardian, $second, 'father');

        $html = $this->actingAs($owner)
            ->withSession(['_old_input' => [
                'form_context' => 'child-update-'.$first->id,
                'child_name' => 'Only this child',
                'date_of_birth' => '2021-02-03',
            ]])
            ->get(route('families.show', $guardian))
            ->assertOk()
            ->getContent();

        $this->assertNotFalse($html);
        $this->assertSame(1, substr_count($html, 'value="Only this child"'));
        $this->assertStringContainsString('value="Second child"', $html);
        $this->assertStringContainsString('name="form_context" value="child-create"', $html);
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

    private function link(Tenant $tenant, User $owner, Guardian $guardian, Child $child, string $relationship): void
    {
        DB::table('guardian_child')->insert([
            'tenant_id' => $tenant->id,
            'guardian_id' => $guardian->id,
            'child_id' => $child->id,
            'relationship_type' => $relationship,
            'is_active' => true,
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
