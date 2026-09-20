<?php

return [
    // One-time credential handoff. This does not claim provider delivery.
    'owner_invitation_expiry_minutes' => (int) env('PLATFORM_OWNER_INVITATION_EXPIRY_MINUTES', 1440),

    'support_access' => [
        // OQ-14 deliberately starts with one narrow, non-operational scope.
        // New scopes require an approved policy and a matching constrained
        // support surface; this is not tenant impersonation.
        'scopes' => [
            'tenant_administration_read' => 'Tenant administration metadata (read-only)',
            'branch_configuration_read' => 'Branch configuration (read-only)',
        ],
        'max_duration_minutes' => 60,
    ],
];
