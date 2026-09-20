<?php

namespace App\Support;

use App\Models\Branch;
use DateTimeZone;
use Illuminate\Support\Collection;

final class BranchReadiness
{
    /**
     * The exact approved activation predicate. Callers supply the branch's
     * already tenant-scoped opening-hours rows.
     */
    public static function isActivationReady(Branch $branch, Collection $hours): bool
    {
        $methods = is_array($branch->payment_methods) ? $branch->payment_methods : [];
        $weekdays = $hours->pluck('weekday')->map(fn ($day): int => (int) $day)->unique()->sort()->values()->all();
        $hoursAreValid = $hours->every(fn ($day): bool => (bool) $day->is_closed
            ? $day->opens_at === null && $day->closes_at === null
            : $day->opens_at !== null && $day->closes_at !== null && $day->opens_at < $day->closes_at);

        return is_string($branch->code) && trim($branch->code) !== ''
            && is_string($branch->name) && trim($branch->name) !== ''
            && in_array($branch->timezone, DateTimeZone::listIdentifiers(), true)
            && (int) $branch->capacity > 0
            && $branch->currency === 'EGP'
            && (int) $branch->tax_rate_bps >= 0 && (int) $branch->tax_rate_bps <= 10_000
            && in_array($branch->tax_mode, ['exclusive', 'inclusive'], true)
            && is_string($branch->receipt_prefix) && trim($branch->receipt_prefix) !== ''
            && $methods === ['cash']
            && $weekdays === [1, 2, 3, 4, 5, 6, 7]
            && $hoursAreValid;
    }
}
