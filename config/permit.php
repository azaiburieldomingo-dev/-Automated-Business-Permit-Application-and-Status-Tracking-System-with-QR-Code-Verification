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
        'mode' => env('PAYMENT_MODE', 'sandbox'),
        'methods' => [
            'gcash' => (bool) env('PAYMENT_GCASH_ENABLED', true),
            'maya' => (bool) env('PAYMENT_MAYA_ENABLED', true),
            'qrph' => (bool) env('PAYMENT_QRPH_ENABLED', true),
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
];
