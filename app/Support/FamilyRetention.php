<?php

namespace App\Support;

use App\Models\FamilyRetentionHold;
use App\Models\Guardian;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class FamilyRetention
{
    public const YEARS = 3;

    /** @return Collection<int, array<string, mixed>> */
    public static function dryRun(Tenant $tenant, int $limit = 200): Collection
    {
        $guardians = Guardian::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereDoesntHave('children', fn ($query) => $query->where('guardian_child.tenant_id', $tenant->getKey())->where('guardian_child.is_active', true))
            ->select(['id', 'tenant_id', 'full_name', 'status', 'updated_at'])
            ->selectSub(function ($query) use ($tenant): void {
                $query->from('play_sessions')->selectRaw('MAX(COALESCE(ended_at, started_at, created_at))')
                    ->whereColumn('play_sessions.guardian_id', 'guardians.id')
                    ->where('play_sessions.tenant_id', $tenant->getKey());
            }, 'last_visit_at')
            ->selectSub(function ($query) use ($tenant): void {
                $query->from('guardian_child')->selectRaw('MAX(COALESCE(revoked_at, updated_at))')
                    ->whereColumn('guardian_child.guardian_id', 'guardians.id')
                    ->where('guardian_child.tenant_id', $tenant->getKey());
            }, 'relationship_closed_at')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $holds = FamilyRetentionHold::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('status', 'active')
            ->whereIn('guardian_id', $guardians->modelKeys())
            ->with('placedBy:id,name')
            ->orderByDesc('placed_at')
            ->get()
            ->keyBy('guardian_id');
        $cutoff = CarbonImmutable::now('UTC')->subYears(self::YEARS);

        return $guardians->map(function (Guardian $guardian) use ($holds, $cutoff): array {
            $dates = collect([$guardian->updated_at, $guardian->last_visit_at, $guardian->relationship_closed_at])
                ->filter()
                ->map(fn ($date) => CarbonImmutable::parse($date, 'UTC'));
            $lastActivity = $dates->max();
            $eligibleAt = $lastActivity?->addYears(self::YEARS);
            $hold = $holds->get($guardian->getKey());

            return [
                'guardian' => $guardian,
                'last_activity_at' => $lastActivity,
                'eligible_at' => $eligibleAt,
                'eligible' => $eligibleAt?->lessThanOrEqualTo(CarbonImmutable::now('UTC')) ?? false,
                'hold' => $hold,
                'blocked' => $hold !== null,
                'cutoff' => $cutoff,
            ];
        })->sortBy(fn (array $row) => $row['eligible_at']?->timestamp ?? PHP_INT_MAX)->values();
    }
}
