<?php

declare(strict_types=1);

return [
    'app' => [
        'base_url' => '/',
        'public_origin' => 'https://ping.example.com',
        'debug' => false,
    ],
    'db' => [
        'dsn' => 'mysql:host=127.0.0.1;dbname=ping_flood_watch;charset=utf8mb4',
        'username' => 'ping_flood_watch',
        'password' => 'replace-with-a-local-secret',
    ],
    'push' => [
        'enabled' => false,
        'subject' => 'https://ping.example.com/',
        'public_key' => 'replace-with-vapid-public-key',
        'private_key' => 'replace-with-vapid-private-key',
    ],
    'security' => [
        'rate_limit_key' => 'replace-with-a-random-local-secret',
    ],
];
