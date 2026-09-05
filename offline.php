<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
?>
<!doctype html>
<html lang="<?= e(locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#0c6db2">
    <script src="<?= e(asset_url('assets/js/theme-init.js')) ?>"></script>
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/app.css')) ?>">
    <title><?= e(t('offline.title')) ?></title>
</head>
<body data-language="<?= e(locale()) ?>" data-base-url="<?= e(url()) ?>" data-default-station="P.1">
<main class="app-shell">
    <div class="main-content">
        <section class="empty-state">
            <span class="brand-mark"><span class="material-symbols">cloud_off</span></span>
            <h1><?= e(t('offline.title')) ?></h1>
            <p class="offline-marker"><?= e(t('common.offline')) ?></p>
            <p><?= e(t('offline.body')) ?></p>
            <a class="language-button" href="<?= e(url()) ?>"><?= e(t('common.retry')) ?></a>
        </section>
        <section id="offline-snapshot" class="card" hidden>
            <p class="kicker"><?= e(t('home.primary')) ?></p>
            <div class="level-reading compact"><span id="offline-level">—</span><small>m</small></div>
            <div class="metric-grid two">
                <div><span><?= e(t('home.river_risk')) ?></span><strong id="offline-river">—</strong></div>
                <div><span><?= e(t('home.rain_24h')) ?></span><strong id="offline-rain">—</strong></div>
            </div>
            <p id="offline-stored" class="muted"></p>
        </section>
    </div>
</main>
<script src="<?= e(asset_url('assets/js/app.js')) ?>" defer></script>
<script src="<?= e(asset_url('assets/js/offline.js')) ?>" defer></script>
</body>
</html>
