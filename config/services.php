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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'bnc' => [
        'enabled' => (bool) env('BNC_ENABLED', false),
        'base_url' => env('BNC_BASE_URL', 'https://servicios.bncenlinea.com:16500'),
        'client_guid' => env('BNC_CLIENT_GUID'),
        'master_key' => env('BNC_MASTER_KEY'),
        'client_id' => env('BNC_CLIENT_ID'),
        'account_number' => env('BNC_ACCOUNT_NUMBER'),
        'currency' => env('BNC_CURRENCY', 'VES'),
        'default_payer_bank_code' => (int) env('BNC_DEFAULT_PAYER_BANK_CODE', 191),
        'sw_test_operation' => (bool) env('BNC_SW_TEST_OPERATION', false),
        'timeout' => (int) env('BNC_TIMEOUT', 20),
        'verify_ssl' => (bool) env('BNC_VERIFY_SSL', true),
        'verify_on_report' => (bool) env('BNC_VERIFY_ON_REPORT', true),
        'require_reference' => (bool) env('BNC_REQUIRE_REFERENCE', true),
        'verification_mode' => env('BNC_VERIFICATION_MODE', 'auto'),
        'request_ttl_days' => (int) env('BNC_REQUEST_TTL_DAYS', 7),
        'force_all_accounts' => (bool) env('BNC_FORCE_ALL_ACCOUNTS', false),
        'account_ids' => array_values(array_filter(array_map(
            'intval',
            explode(',', (string) env('BNC_ACCOUNT_IDS', ''))
        ))),
        'amount_tolerance' => (float) env('BNC_AMOUNT_TOLERANCE', 0.01),
        'webhook' => [
            'auth' => env('BNC_WEBHOOK_AUTH', 'api_key'),
            'api_key' => env('BNC_WEBHOOK_API_KEY'),
            'login' => env('BNC_WEBHOOK_LOGIN'),
            'password' => env('BNC_WEBHOOK_PASSWORD'),
            'token_ttl' => (int) env('BNC_WEBHOOK_TOKEN_TTL', 3600),
        ],
    ],

];
