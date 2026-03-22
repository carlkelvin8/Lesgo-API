<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    // =========
    // Twilio (SMS — global)
    // =========
    'twilio' => [
        'sid'   => env('TWILIO_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'from'  => env('TWILIO_FROM'),
    ],

    // =========
    // Semaphore (SMS — PH fallback)
    // =========
    'semaphore' => [
        'api_key'     => env('SEMAPHORE_API_KEY'),
        'sender_name' => env('SEMAPHORE_SENDER_NAME', 'LESGO'),
    ],

    // =========
    // PayMongo (PH payment gateway)
    // =========
    'paymongo' => [
        'public_key'     => env('PAYMONGO_PUBLIC_KEY'),
        'secret_key'     => env('PAYMONGO_SECRET_KEY'),
        'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET'),
        'base_url'       => env('PAYMONGO_BASE_URL', 'https://api.paymongo.com/v1'),
    ],

    // =========
    // GCash / Maya webhook secrets
    // =========
    'gcash' => [
        'webhook_secret' => env('GCASH_WEBHOOK_SECRET'),
    ],

    'maya' => [
        'webhook_secret' => env('MAYA_WEBHOOK_SECRET'),
    ],

];
