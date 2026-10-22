<?php

/*
| Field staff mobile app policy. Everything the business may want to tune
| without a code change lives here; defaults are the conservative choice.
*/
return [

    // Roles allowed to sign in to the field app.
    'roles' => ['staff'],

    // Business day boundaries ("today" on the dashboard, attendance date).
    'timezone' => env('FIELDAPP_TIMEZONE', env('APP_TIMEZONE', 'UTC')),

    // Bearer token lifetime; /auth/refresh rotates a still-valid token.
    'token_ttl_minutes' => (int) env('FIELDAPP_TOKEN_TTL_MINUTES', 60 * 24 * 7),

    'attendance' => [
        // off: ignore geofence | flag: record inside/outside | enforce: reject outside.
        'geofence_mode' => env('FIELDAPP_GEOFENCE_MODE', 'flag'),
        // Reject fixes coarser than this (metres) when enforcing; null = accept any.
        'max_accuracy_m' => env('FIELDAPP_MAX_ACCURACY_M') !== null ? (int) env('FIELDAPP_MAX_ACCURACY_M') : null,
        // When true, orders and collections require an open check-in today.
        'require_check_in' => (bool) env('FIELDAPP_REQUIRE_CHECK_IN', false),
    ],

    'collections' => [
        // Methods that must carry a bank/UPI/cheque reference.
        'reference_required' => ['upi', 'bank_transfer', 'cheque'],
        // How far back a client-reported collection time may be.
        'max_backdate_days' => 7,
    ],

    'expenses' => [
        'categories' => ['travel', 'fuel', 'food', 'lodging', 'phone', 'courier', 'stationery', 'other'],
        'max_amount' => 100000,
        'max_age_days' => 30,
        // Amounts at or below this are auto-approved; null = every expense needs review.
        'auto_approve_upto' => env('FIELDAPP_EXPENSE_AUTO_APPROVE_UPTO') ?: null,
        'receipt_disk' => 'local',
        'receipt_max_kb' => 5120,
    ],
];
