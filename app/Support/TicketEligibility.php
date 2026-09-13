<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\Ticket;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class TicketEligibility
{
    public static function familyQuery(Tenant $tenant): Builder
    {
        return DB::table('guardian_child')
            ->join('guardians', function ($join): void {
                $join->on('guardians.tenant_id', '=', 'guardian_child.tenant_id')
                    ->on('guardians.id', '=', 'guardian_child.guardian_id');
            })
            ->join('children', function ($join): void {
                $join->on('children.tenant_id', '=', 'guardian_child.tenant_id')
                    ->on('children.id', '=', 'guardian_child.child_id');
            })
            ->where('guardian_child.tenant_id', $tenant->getKey())
            ->where('guardian_child.is_active', true)
            ->where('guardians.status', 'active')
            ->where('children.status', 'active')
            ->whereNotNull('guardian_child.verified_at')
            ->where('guardian_child.can_check_out', true)
            ->whereNotNull('children.emergency_contact_name')
            ->where('children.emergency_contact_name', '<>', '')
            ->whereNotNull('children.emergency_contact_phone_e164')
            ->where('children.emergency_contact_phone_e164', '<>', '')
            ->where(function (Builder $query): void {
                $query->select('status')->from('family_consent_events')
                    ->whereColumn('family_consent_events.tenant_id', 'children.tenant_id')
                    ->whereColumn('family_consent_events.child_id', 'children.id')
                    ->where('consent_type', 'child_data')->orderByDesc('occurred_at')->orderByDesc('id')->limit(1);
            }, 'granted');
    }

    public static function result(?Ticket $ticket, Branch $branch): string
    {
        if (! $ticket || (int) $ticket->tenant_id !== (int) $branch->tenant_id) {
            return 'not_found';
        }
        if ((int) $ticket->branch_id !== (int) $branch->getKey()) {
            return 'wrong_branch';
        }
        if ($ticket->status === 'cancelled') {
            return 'cancelled';
        }
        if ($ticket->status === 'consumed' || $ticket->consumed_at !== null || (int) $ticket->uses_count >= (int) $ticket->max_uses) {
            return 'already_consumed';
        }
        if ($ticket->status !== 'issued') {
            return 'expired';
        }

        $now = CarbonImmutable::now('UTC');
        if ($now->setTimezone($ticket->price_snapshot_json['branch_timezone'])->toDateString() !== $ticket->service_date->format('Y-m-d')) {
            return 'wrong_service_date';
        }
        if ($now->lessThan($ticket->valid_from) || $now->greaterThanOrEqualTo($ticket->valid_until)) {
            return 'expired';
        }
        if (! self::familyQuery($branch->tenant)->where('guardian_child.guardian_id', $ticket->guardian_id)
            ->where('guardian_child.child_id', $ticket->child_id)->lockForUpdate()->first(['guardian_child.guardian_id'])) {
            return 'family_unavailable';
        }

        return 'accepted';
    }
}
