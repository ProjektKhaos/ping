#!/usr/bin/env php
<?php

declare(strict_types=1);

use Minishlink\WebPush\VAPID;

if (PHP_SAPI !== 'cli' || function_exists('posix_geteuid') && posix_geteuid() !== 0) {
    fwrite(STDERR, "Run this command as root from the release directory.\n");
    exit(77);
}

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
$path = '/etc/ping-flood-watch/config.php';
if (!is_file($path)) {
    fwrite(STDERR, "Production configuration does not exist.\n");
    exit(66);
}
$config = require $path;
if (!is_array($config)) {
    fwrite(STDERR, "Production configuration is invalid.\n");
    exit(65);
}

$publicKey = (string) ($config['push']['public_key'] ?? '');
$privateKey = (string) ($config['push']['private_key'] ?? '');
if ($publicKey === '' || $privateKey === '') {
    $keys = VAPID::createVapidKeys();
    $publicKey = $keys['publicKey'];
    $privateKey = $keys['privateKey'];
}
$rateLimitKey = (string) ($config['security']['rate_limit_key'] ?? '');
if ($rateLimitKey === '') {
    $rateLimitKey = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
}

$config['app']['public_origin'] = 'https://ping.aberg.online';
$config['app']['asset_version'] = '1.2.4';
$config['push'] = array_replace($config['push'] ?? [], [
    'enabled' => true,
    'subject' => 'https://ping.aberg.online/',
    'public_key' => $publicKey,
    'private_key' => $privateKey,
]);
$config['security'] = array_replace($config['security'] ?? [], ['rate_limit_key' => $rateLimitKey]);

$temporary = tempnam(dirname($path), '.config-v1.1-');
if ($temporary === false) {
    throw new RuntimeException('Unable to create a secure temporary configuration file.');
}
try {
    $contents = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";
    if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
        throw new RuntimeException('Unable to write production configuration.');
    }
    chmod($temporary, 0640);
    chown($temporary, 'root');
    chgrp($temporary, 'www-data');
    if (!rename($temporary, $path)) {
        throw new RuntimeException('Unable to replace production configuration atomically.');
    }
} finally {
    if (is_file($temporary)) {
        unlink($temporary);
    }
}

fwrite(STDOUT, "Production VAPID and rate-limit configuration is present; secret values were not printed.\n");
