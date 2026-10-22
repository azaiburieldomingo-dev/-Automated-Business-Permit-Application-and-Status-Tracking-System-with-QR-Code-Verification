<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Municipality Identity
    |--------------------------------------------------------------------------
    */
    'municipality' => env('MUNICIPALITY_NAME', 'Municipality of Bansud'),
    'province' => env('MUNICIPALITY_PROVINCE', 'Oriental Mindoro'),

    /*
    |--------------------------------------------------------------------------
    | Tracking / Permit Numbering
    |--------------------------------------------------------------------------
    */
    'tracking_prefix' => 'BANSUD',
    'permit_prefix' => 'BP',

    /*
    |--------------------------------------------------------------------------
    | Permit Validity
    |--------------------------------------------------------------------------
    */
    'valid_months' => (int) env('PERMIT_VALID_MONTHS', 12),

    /*
    |--------------------------------------------------------------------------
    | QR Verification Security
    |--------------------------------------------------------------------------
    */
    'qr_hash_salt' => env('QR_HASH_SALT', 'change-this'),

    /*
    | Address that phones use to open /verify/{token} after scanning a permit's
    | QR code. Must be reachable from the inspector's phone: never "localhost".
    | Use the PC's Wi-Fi IP for local testing, the real domain when deployed.
    */
    'public_url' => env('PERMIT_PUBLIC_URL', env('APP_URL', 'http://localhost')),

    /*
    |--------------------------------------------------------------------------
    | SMS Gateway
    |--------------------------------------------------------------------------
    */
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'api_key' => env('SMS_API_KEY'),
        'api_secret' => env('SMS_API_SECRET'),
        'sender_id' => env('SMS_SENDER_ID', 'BANSUD'),
        'base_url' => env('SMS_BASE_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway
    |--------------------------------------------------------------------------
    */
    'payment' => [
        // manual  = applicant sends money to the account below, submits the
        //           reference number + receipt, and the BPLO verifies it.
        // sandbox = simulated gateway for testing (no real money).
        'mode' => env('PAYMENT_MODE', 'manual'),
        'methods' => [
            'gcash' => (bool) env('PAYMENT_GCASH_ENABLED', true),
            'maya' => (bool) env('PAYMENT_MAYA_ENABLED', true),
            'qrph' => (bool) env('PAYMENT_QRPH_ENABLED', true),
        ],
        // Receiving accounts shown to applicants in manual mode. A method with
        // no account number is shown as "not yet available".
        'accounts' => [
            'gcash' => [
                'label' => 'GCash',
                'number' => env('PAYMENT_GCASH_NUMBER'),
                'name' => env('PAYMENT_GCASH_NAME'),
            ],
            'maya' => [
                'label' => 'Maya',
                'number' => env('PAYMENT_MAYA_NUMBER'),
                'name' => env('PAYMENT_MAYA_NAME'),
            ],
            'qrph' => [
                'label' => 'QR Ph',
                'number' => env('PAYMENT_QRPH_NUMBER'),
                'name' => env('PAYMENT_QRPH_NAME'),
            ],
        ],
        'gateway_key' => env('PAYMENT_GATEWAY_KEY'),
        'gateway_secret' => env('PAYMENT_GATEWAY_SECRET'),
        'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Upload Mime Types / Size (KB)
    |--------------------------------------------------------------------------
    */
    'uploads' => [
        'mimes' => ['pdf', 'jpg', 'jpeg', 'png'],
        'max_kb' => 4096,
    ],

    /*
    |--------------------------------------------------------------------------
    | Renewal Reminder
    |--------------------------------------------------------------------------
    | How many days before a permit's validity expires the applicant portal
    | begins reminding the owner to renew. Reference everywhere as
    | config('permit.renewal.reminder_days', 30).
    */
    'renewal' => [
        'reminder_days' => (int) env('PERMIT_RENEWAL_REMINDER_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Help / Support Contact
    |--------------------------------------------------------------------------
    | BPLO office details surfaced on the applicant Help page. All default to
    | null when the matching env key is absent; the view renders a muted
    | "Not yet provided" rather than fabricating a value.
    */
    'support' => [
        'office_hours' => env('BPLO_OFFICE_HOURS'),
        'office_address' => env('BPLO_OFFICE_ADDRESS'),
        'contact_phone' => env('BPLO_CONTACT_PHONE'),
        'contact_email' => env('BPLO_CONTACT_EMAIL'),
    ],
];
