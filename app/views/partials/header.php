<?php
$pageTitle = $pageTitle ?? t('app.name');
$activeNav = $activeNav ?? 'home';
$lang = locale();
$otherLang = $lang === 'th' ? 'en' : 'th';
$pageDescription = t('app.tagline');
$publicOrigin = rtrim((string) \PingFloodWatch\Config::get('app.public_origin', 'https://ping.aberg.online'), '/');
$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? url()), PHP_URL_PATH);
$basePath = url();
if (!is_string($requestPath) || !str_starts_with($requestPath, $basePath)) {
    $requestPath = $basePath;
}
$canonicalUrl = $publicOrigin . '/' . ltrim($requestPath, '/');
$ogImageUrl = $publicOrigin . url('og-facebook.png');
\PingFloodWatch\Services\VisitorCounter::record($lang);
?>
<!doctype html>
<html lang="<?= e($lang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <meta name="theme-color" content="#0c6db2">
    <meta name="description" content="<?= e($pageDescription) ?>">
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="<?= e($lang === 'th' ? 'th_TH' : 'en_US') ?>">
    <meta property="og:site_name" content="<?= e(t('app.name')) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <meta property="og:url" content="<?= e($canonicalUrl) ?>">
    <meta property="og:image" content="<?= e($ogImageUrl) ?>">
    <meta property="og:image:secure_url" content="<?= e($ogImageUrl) ?>">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Ping Flood Watch — Chiang Mai river and rainfall watch">
    <script src="<?= e(asset_url('assets/js/theme-init.js')) ?>"></script>
    <link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
    <link rel="icon" href="<?= e(asset_url('ping_logo1.png')) ?>" type="image/png">
    <?php if (!empty($pageStyles) && is_array($pageStyles)): foreach ($pageStyles as $stylesheet): ?>
    <link rel="stylesheet" href="<?= e(asset_url($stylesheet)) ?>">
    <?php endforeach; endif; ?>
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/app.css?layout=desktop-1')) ?>">
    <title><?= e($pageTitle) ?></title>
</head>
<body data-language="<?= e($lang) ?>" data-base-url="<?= e(url()) ?>" data-default-station="<?= e((string) \PingFloodWatch\Config::get('stations.default', 'P.1')) ?>">
<a class="skip-link" href="#main"><?= e(t('a11y.skip')) ?></a>
<div class="app-shell">
    <header class="app-bar">
        <a class="brand" href="<?= e(url('?lang=' . $lang)) ?>" aria-label="<?= e(t('app.name')) ?>">
            <span class="brand-mark" aria-hidden="true"><img src="<?= e(asset_url('ping_logo1.png')) ?>" alt=""></span>
            <span class="brand-copy"><strong><?= e(t('app.name')) ?></strong><small><?= e(t('app.tagline')) ?></small></span>
        </a>
        <nav class="desktop-nav" aria-label="<?= e(t('a11y.primary_nav')) ?>">
            <a class="nav-home" href="<?= e(url('?lang=' . $lang)) ?>"<?= $activeNav === 'home' ? ' aria-current="page"' : '' ?>><span class="material-symbols" aria-hidden="true">home</span><span><?= e(t('nav.home')) ?></span></a>
            <a class="nav-stations" href="<?= e(url('stations.php?lang=' . $lang)) ?>"<?= $activeNav === 'stations' ? ' aria-current="page"' : '' ?>><span class="material-symbols" aria-hidden="true">waves</span><span><?= e(t('nav.stations')) ?></span></a>
            <a class="nav-alerts" href="<?= e(url('alerts.php?lang=' . $lang)) ?>"<?= $activeNav === 'alerts' ? ' aria-current="page"' : '' ?>><span class="material-symbols" aria-hidden="true">notifications_active</span><span><?= e(t('nav.alerts')) ?></span></a>
        </nav>
        <div class="app-actions">
            <button id="theme-toggle" class="theme-button" type="button"
                    data-system="<?= e(t('theme.system')) ?>" data-light="<?= e(t('theme.light')) ?>" data-dark="<?= e(t('theme.dark')) ?>"
                    data-label="<?= e(t('theme.change', ['mode' => '{mode}'])) ?>" aria-label="<?= e(t('a11y.theme')) ?>">
                <span class="material-symbols" aria-hidden="true">brightness_auto</span>
            </button>
            <a class="language-button" href="<?= e(language_url($otherLang)) ?>" hreflang="<?= e($otherLang) ?>" aria-label="<?= e(t('language.label')) ?>"><?= e(t('language.switch')) ?></a>
        </div>
    </header>
    <div id="offline-banner" class="offline-banner" role="status" hidden
         data-offline="<?= e(t('common.offline')) ?>"
         data-update-failed="<?= e(t('common.update_failed')) ?>"><?= e(t('common.offline')) ?></div>
    <main id="main" class="main-content">
