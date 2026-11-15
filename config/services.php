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

    'gemini' => [
        'base_url' => env('GEMINI_BASE_URL', 'http://127.0.0.1:8045/v1'),
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.8-flash-medium'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 30),
    ],

    'nasa' => [
        'api_key' => env('NASA_API_KEY', 'DEMO_KEY'),
        'donki_url' => env('NASA_DONKI_URL', 'https://api.nasa.gov/DONKI'),
        'donki_ccmc_url' => env('NASA_DONKI_CCMC_URL', 'https://kauai.ccmc.gsfc.nasa.gov/DONKI/WS/get'),
        'osdr_url' => env('NASA_OSDR_URL', 'https://visualization.osdr.nasa.gov/biodata/api/v2'),
        'timeout' => (int) env('NASA_API_TIMEOUT', 6),
    ],

];
