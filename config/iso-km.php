<?php

return [
    // Per-deployment white-label settings. Data boundaries stay tied to management systems.
    // Fail closed after merge to main; enable only after role/data/UAT verification.
    'guided_assessment_enabled' => env('ISO_KM_GUIDED_ASSESSMENT_ENABLED', false),
    // Existing production roles are preserved unless their owner opts in to additive baseline grants.
    'role_seeder_reconcile_existing' => env('ISO_KM_ROLE_SEEDER_RECONCILE_EXISTING', false),
    // Explicit recovery if portal.access was created on an earlier deployment.
    'role_seeder_backfill_existing_user_access' => env('ISO_KM_ROLE_SEEDER_BACKFILL_EXISTING_USERS', false),
    'brand_name' => env('ISO_KM_BRAND_NAME', env('APP_NAME', 'ISO-KM')),
    'brand_logo' => env('ISO_KM_BRAND_LOGO'),
    'primary_color' => env('ISO_KM_PRIMARY_COLOR', '#2563eb'),
    // Never derive the organization name from an uploaded example template.
    'organization_name' => env('ISO_KM_ORGANIZATION_NAME'),
    'assessor_role' => env('ISO_KM_ASSESSOR_ROLE'),
];
