<?php

use App\Actions\CollectOperationalNotifications;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('notifications:collect', function (): void {
    $result = app(CollectOperationalNotifications::class)->run();
    $this->info("Created {$result['created']}; dispatched {$result['dispatched']}.");
})->purpose('Collect due local operational notification intents');

Schedule::command('notifications:collect')->everyMinute()->withoutOverlapping();
Schedule::command('subscriptions:sync-status')->everyMinute()->withoutOverlapping();
