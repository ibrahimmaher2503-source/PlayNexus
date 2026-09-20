<?php

namespace App\Console\Commands;

use App\Actions\SyncSubscriptionStatuses as SyncSubscriptionStatusesAction;
use Illuminate\Console\Command;

class SyncSubscriptionStatuses extends Command
{
    protected $signature = 'subscriptions:sync-status';

    protected $description = 'Materialize due SaaS subscription lifecycle and commercial override expirations';

    public function handle(SyncSubscriptionStatusesAction $sync): int
    {
        $result = $sync->run();
        $this->info("Status changes: {$result['status_changes']}; expired overrides: {$result['expired_overrides']}.");

        return self::SUCCESS;
    }
}
