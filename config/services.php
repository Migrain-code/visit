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

    /*
     * Google reCAPTCHA.
     *
     * İki sürüm de desteklenir. Google'dan hangisini aldıysanız RECAPTCHA_VERSION
     * onu göstermelidir; anahtarlar sürüme özeldir ve birbirinin yerine geçmez.
     *   v3 → görünmez, puan tabanlı (varsayılan, kullanıcıyı hiç durdurmaz)
     *   v2 → "Ben robot değilim" kutucuğu
     *
     * İki anahtardan biri boşsa doğrulama tamamen devre dışı kalır: yarım
     * yapılandırmayla formu kilitlemek, gerçek müşteriyi kapıda bırakır.
     */
    'recaptcha' => [
        'site_key' => env('RECAPTCHA_SITE_KEY'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY'),
        'version' => env('RECAPTCHA_VERSION', 'v3'),
        // v3 puanı: 1.0 kesin insan, 0.0 kesin bot. Google 0.5'i başlangıç olarak önerir.
        'min_score' => (float) env('RECAPTCHA_MIN_SCORE', 0.5),
        'action' => 'rezervasyon_formu',
        'timeout' => (int) env('RECAPTCHA_TIMEOUT', 5),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
