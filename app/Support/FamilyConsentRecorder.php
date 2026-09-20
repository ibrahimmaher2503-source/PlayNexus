<?php

namespace App\Support;

use App\Http\Controllers\FamilyController;
use App\Models\Child;
use App\Models\Guardian;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FamilyConsentRecorder
{
    public static function record(
        User $actor,
        Tenant $tenant,
        Guardian $guardian,
        Child $child,
        string $type,
        string $status,
        string $locale,
        string $requestId,
    ): void {
        $now = now('UTC');

        DB::table('family_consent_events')->insert([
            'tenant_id' => $tenant->getKey(),
            'guardian_id' => $guardian->getKey(),
            'child_id' => $child->getKey(),
            'consent_type' => $type,
            'status' => $status,
            'notice_version' => FamilyController::NOTICE_VERSION,
            'purpose_snapshot' => $type === 'child_data' ? 'family_registration_and_safe_venue_operation' : 'direct_marketing',
            'data_categories_snapshot' => $type === 'child_data' ? 'identity_contact_age_emergency_safety' : 'guardian_contact',
            'locale' => $locale,
            'method' => 'electronic',
            'actor_user_id' => $actor->getKey(),
            'branch_id' => null,
            'request_id' => $requestId,
            'occurred_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
