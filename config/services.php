<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'simplepay' => [
        'merchant' => env('SIMPLEPAY_MERCHANT'),
        'secret_key' => env('SIMPLEPAY_SECRET_KEY'),
        'sandbox' => env('SIMPLEPAY_SANDBOX', true),
    ],

    'barion' => [
        'pos_key' => env('BARION_POS_KEY'),
        'payee' => env('BARION_PAYEE'),
        'sandbox' => env('BARION_SANDBOX', true),
    ],

    'hcaptcha' => [
        'enabled' => env('HCAPTCHA_ENABLED', false),
        'site_key' => env('HCAPTCHA_SITE_KEY'),
        'secret' => env('HCAPTCHA_SECRET'),
    ],

];
