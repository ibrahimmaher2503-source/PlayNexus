<?php

return [
    // Approved OQ-22 values. Absolute lifetime awaits DEC-SEC-01.
    'staff_idle_minutes' => 30,
    'privileged_idle_minutes' => 15,
    'absolute_minutes' => env('AUTH_ABSOLUTE_MINUTES'),
    'owner_mfa_required' => (bool) env('OWNER_MFA_REQUIRED', false),
];
