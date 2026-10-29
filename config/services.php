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

    'whatsapp' => [
        'enabled' => env('WHATSAPP_ENABLED', false),
        'api_version' => env('WHATSAPP_CLOUD_API_VERSION'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'test_mode' => env('WHATSAPP_TEST_MODE', true),
        'test_recipients' => array_filter(array_map('trim', explode(',', (string) env('WHATSAPP_TEST_RECIPIENTS', '')))),
        'quote_template' => env('WHATSAPP_QUOTE_TEMPLATE'),
        'template_language' => env('WHATSAPP_TEMPLATE_LANGUAGE', 'es_CL'),
    ],

    'cotizaciones' => [
        'reserva_horas' => (int) env('COTIZACION_RESERVA_HORAS', 48),
        'vigencia_dias' => (int) env('COTIZACION_VIGENCIA_DIAS', 7),
        'costo_despacho' => (float) env('COTIZACION_COSTO_DESPACHO', 0),
    ],

];
