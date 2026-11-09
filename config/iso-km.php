<?php

return [
    // Per-deployment white-label settings. Data boundaries stay tied to management systems.
    'brand_name' => env('ISO_KM_BRAND_NAME', env('APP_NAME', 'ISO-KM')),
    'brand_logo' => env('ISO_KM_BRAND_LOGO'),
    'primary_color' => env('ISO_KM_PRIMARY_COLOR', '#2563eb'),
];
