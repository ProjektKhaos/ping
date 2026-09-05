<?php

declare(strict_types=1);

return [
    'app' => [
        'base_url' => '/',
        'public_origin' => 'http://127.0.0.1:8765',
        'asset_version' => '1.2.3',
        'debug' => false,
    ],
    'db' => [
        'dsn' => getenv('PFW_TEST_DSN') ?: '',
        'username' => getenv('PFW_TEST_DB_USER') ?: '',
        'password' => getenv('PFW_TEST_DB_PASSWORD') ?: '',
    ],
    'push' => [
        'enabled' => false,
        'subject' => 'http://127.0.0.1:8765/',
        'public_key' => '',
        'private_key' => '',
    ],
    'security' => [
        'rate_limit_key' => 'isolated-staging-rate-limit-key-not-for-production',
    ],
    'storage' => [
        'logs' => dirname(__DIR__) . '/storage/logs',
        'locks' => dirname(__DIR__) . '/storage/locks',
        'cache' => dirname(__DIR__) . '/storage/cache',
    ],
];
