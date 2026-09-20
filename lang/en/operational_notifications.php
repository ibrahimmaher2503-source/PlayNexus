<?php

return [
    'page_title' => 'Operational notifications', 'description' => 'Database-transport intent and attempt status. Sent means local transport acceptance; delivery is not claimed.',
    'branch' => 'Branch', 'all_branches' => 'All permitted branches', 'purpose' => 'Purpose', 'status' => 'Status', 'all' => 'All', 'apply' => 'Apply filters', 'results' => 'Notification history', 'empty' => 'No notification intents match these filters.',
    'created' => 'Created', 'destination' => 'Masked destination', 'attempts' => 'Attempts',
    'purposes' => ['session_ending' => 'Session ending', 'receipt' => 'Receipt'],
    'statuses' => ['queued' => 'Queued', 'sent' => 'Sent', 'delivered' => 'Delivered', 'failed_retryable' => 'Retry pending', 'failed_permanent' => 'Failed permanently', 'stale' => 'Stale'],
];
