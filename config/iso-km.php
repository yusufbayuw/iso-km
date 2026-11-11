<?php

return [
    // Per-deployment white-label settings. Data boundaries stay tied to management systems.
    // Fail closed after merge to main; enable only after role/data/UAT verification.
    'guided_assessment_enabled' => env('ISO_KM_GUIDED_ASSESSMENT_ENABLED', false),
    // New guided wizard can be rolled out separately after role/catalog/UAT checks.
    'guided_wizard_enabled' => env('ISO_KM_GUIDED_WIZARD_ENABLED', false),
    'brand_name' => env('ISO_KM_BRAND_NAME', env('APP_NAME', 'ISO-KM')),
    'brand_logo' => env('ISO_KM_BRAND_LOGO'),
    'primary_color' => env('ISO_KM_PRIMARY_COLOR', '#2563eb'),
    // Never derive the organization name from an uploaded example template.
    'organization_name' => env('ISO_KM_ORGANIZATION_NAME'),
    'assessor_role' => env('ISO_KM_ASSESSOR_ROLE'),
];
