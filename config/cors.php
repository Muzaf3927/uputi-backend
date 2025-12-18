<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/auth'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://ride-share-pro.netlify.app', // ← Netlify фронт
        'https://uputi.net',                  // новый основной домен
        'https://www.uputi.net',
        'https://uputimaster.vercel.app', //master dev
    ],

    'allowed_origins_patterns' => [
        '/^http:\/\/localhost:\d+$/', // разрешает любой localhost порт
        '/^http:\/\/127\.0\.0\.1:\d+$/', // разрешает 127.0.0.1 с любым портом
        '/^http:\/\/192\.168\.\d+\.\d+:\d+$/', // разрешает локальную сеть
        '/^https:\/\/.*\.vercel\.app$/', // разрешает все Vercel домены
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
