<?php

/*
 * "E-mail to client" (owner, 17 Nov 2026): sub-bills and tax invoices are sent from the agency's own Gmail,
 * using a Gmail APP PASSWORD kept only in the server's environment (Coolify). Never put a password here.
 * An agency without both values simply cannot send (the button explains how to switch it on).
 */
return [
    'host' => env('AGENCY_MAIL_HOST', 'smtp.gmail.com'),
    'port' => (int) env('AGENCY_MAIL_PORT', 587),
    'timeout' => (int) env('AGENCY_MAIL_TIMEOUT', 20),

    // Keyed by agency code (also found through the bill-number prefix: SW -> SWH, ZP -> ZPS).
    'agencies' => [
        'SWH' => ['username' => env('AGENCY_MAIL_SWH_USERNAME'), 'password' => env('AGENCY_MAIL_SWH_PASSWORD')],
        'ZPS' => ['username' => env('AGENCY_MAIL_ZPS_USERNAME'), 'password' => env('AGENCY_MAIL_ZPS_PASSWORD')],
    ],
    'prefixes' => ['SW' => 'SWH', 'ZP' => 'ZPS'],

    // Tests only: send through the app's default mailer (Mail::fake) instead of Gmail.
    'use_default_mailer' => (bool) env('AGENCY_MAIL_USE_DEFAULT', false),
];
