<?php

return [
    'eyebrow' => 'Owner workspace', 'title' => 'Venue setup',
    'description' => 'Review the configuration already saved for this venue. This guide does not change settings or activate anything.',
    'required_progress' => ':complete of :total core setup items ready', 'steps_heading' => 'Setup guide',
    'complete' => 'Ready', 'preparation' => 'Preparation', 'action_needed' => 'Review needed',
    'note' => 'Staff, pricing rules, and POS products are preparation items, not branch activation gates. A valid pricing rule is still required when starting a priced session.',
    'steps' => [
        'profile' => ['title' => 'Business profile', 'description' => 'Check the saved business name, legal name, language, timezone, and currency.', 'action' => 'Open business profile'],
        'branches' => ['title' => 'Branches and readiness', 'description' => ':ready_count ready branch(es) out of :count saved. Active branches are available to operations.', 'action' => 'Manage branches'],
        'staff' => ['title' => 'Staff and assignments', 'description' => ':count active account(s), with :assigned_count assigned to a branch.', 'action' => 'Manage staff'],
        'pricing' => ['title' => 'Operational pricing', 'description' => ':count active pricing rule(s) are available. A valid rule is required before a priced session can start.', 'action' => 'Open pricing'],
        'catalog' => ['title' => 'POS catalog', 'description' => ':count active product(s) are available.', 'action' => 'Open catalog'],
    ],
];
