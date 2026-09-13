<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return app(PricingRulePolicy::class)->viewAny($user);
    }

    public function view(User $user, Ticket $ticket): bool
    {
        $fresh = Ticket::query()->whereKey($ticket->getKey())->where('tenant_id', $user->tenant_id)->first();
        $branch = $fresh ? Branch::query()->whereKey($fresh->branch_id)->where('tenant_id', $fresh->tenant_id)->first() : null;

        return $branch !== null && app(PricingRulePolicy::class)->viewBranch($user, $branch);
    }

    public function createType(User $user, Branch $branch): bool
    {
        return app(PricingRulePolicy::class)->canCreateBranch($user, $branch);
    }

    public function issue(User $user, Branch $branch): bool
    {
        return app(PricingRulePolicy::class)->viewBranch($user, $branch);
    }

    public function scan(User $user, Branch $branch): bool
    {
        return $this->issue($user, $branch);
    }

    public function reassign(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket);
    }

    public function cancel(User $user, Ticket $ticket): bool
    {
        $fresh = Ticket::query()->whereKey($ticket->getKey())->where('tenant_id', $user->tenant_id)->first();
        $branch = $fresh ? Branch::query()->whereKey($fresh->branch_id)->where('tenant_id', $fresh->tenant_id)->first() : null;

        return $branch !== null && app(PricingRulePolicy::class)->canCreateBranch($user, $branch);
    }

    public function reprint(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket);
    }
}
