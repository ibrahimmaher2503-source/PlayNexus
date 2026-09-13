<?php

namespace App\Support;

use App\Models\Child;
use App\Models\Guardian;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class FamilyPresenter
{
    public function __construct(private readonly User $actor) {}

    /** @return array<string, mixed> */
    public function guardian(Guardian $guardian, Collection $children): array
    {
        $sensitive = Gate::forUser($this->actor)->allows('viewSensitiveData', $guardian);

        return [
            'id' => (int) $guardian->getKey(),
            'full_name' => $guardian->full_name,
            'phone_e164' => $sensitive ? $guardian->phone_e164 : $guardian->maskedPhone(),
            'email' => $sensitive ? $guardian->email : $this->maskedEmail($guardian->email),
            'preferred_locale' => $sensitive ? $guardian->preferred_locale : null,
            'status' => $guardian->status,
            'lock_version' => (int) $guardian->lock_version,
            'children' => $children->map(fn (Child $child): array => $this->child($child, $sensitive))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function child(Child $child, bool $sensitive = true): array
    {
        return [
            'id' => (int) $child->getKey(),
            'full_name' => $child->full_name,
            'date_of_birth' => $sensitive ? $child->date_of_birth?->format('Y-m-d') : null,
            'age' => $child->date_of_birth?->age,
            'emergency_contact_name' => $sensitive ? $child->emergency_contact_name : null,
            'emergency_contact_phone_e164' => $sensitive ? $child->emergency_contact_phone_e164 : null,
            'has_safety_notes' => $sensitive && filled($child->safety_notes_encrypted),
            'safety_notes' => $sensitive ? $child->safety_notes_encrypted : null,
            'child_data_consent_status' => $sensitive ? $this->latestConsentStatus($child, 'child_data') : null,
            'status' => $child->status,
            'lock_version' => (int) $child->lock_version,
        ];
    }

    private function latestConsentStatus(Child $child, string $type): ?string
    {
        return DB::table('family_consent_events')
            ->where('tenant_id', $child->tenant_id)
            ->where('child_id', $child->getKey())
            ->where('consent_type', $type)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->value('status');
    }

    private function maskedEmail(?string $email): ?string
    {
        if (! $email || ! str_contains($email, '@')) {
            return null;
        }

        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 1).'••••@'.$domain;
    }
}
