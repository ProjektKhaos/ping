<?php
$primary = $dashboard['primary'];
$river = $dashboard['risks']['river'];
$weather = $dashboard['risks']['weather'];
$combined = $dashboard['risks']['combined'];
$weatherContext = $weather['context'] ?? [];
$warningThreshold = $dashboard['thresholds']['warning']['value_m'] ?? null;
?>
<section class="hero-heading">
    <p class="eyebrow"><?= e(t('home.eyebrow')) ?></p>
    <div><h1><?= e(t('home.title')) ?></h1><span class="status-dot" aria-hidden="true"></span></div>
</section>

<?php if ($loadError): ?>
<div class="notice notice-unknown" role="alert"><strong><?= e(t('error.title')) ?></strong><span><?= e(t('error.body')) ?></span></div>
<?php endif; ?>
<div id="data-stale-banner" class="notice notice-stale" role="status"<?= in_array((string) ($primary['freshness_status'] ?? 'offline'), ['live', 'delayed'], true) ? ' hidden' : '' ?>><?= e(t('common.data_stale')) ?></div>

<div class="dashboard-layout">
<div class="dashboard-main">
<section class="card level-card" aria-labelledby="primary-title"
         data-role-upstream="<?= e(t('station.role.upstream')) ?>"
         data-role-primary="<?= e(t('station.role.primary')) ?>"
         data-role-downstream="<?= e(t('station.role.downstream')) ?>">
    <label class="home-station-picker" for="home-station-select">
        <span><?= e(t('home.station_picker')) ?></span>
        <select id="home-station-select">
            <?php foreach ($dashboard['stations'] as $option): ?>
            <option value="<?= e($option['provider_station_code']) ?>"<?= (int) $option['is_primary'] === 1 ? ' selected' : '' ?>><?= e($option['provider_station_code'] . ' · ' . $option['display_name_' . locale()]) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <div class="card-heading">
        <div><p class="kicker"><?= e(t('common.current')) ?></p><h2 id="primary-title"><?= e(($primary['provider_station_code'] ?? 'P.1') . ' · ' . ($primary['display_name_' . locale()] ?? t('home.primary'))) ?></h2></div>
        <span id="primary-freshness" class="badge badge-<?= e($primary['freshness_status'] ?? 'offline') ?>"><?= e(t('freshness.' . ($primary['freshness_status'] ?? 'offline'))) ?></span>
    </div>
    <div class="level-reading"><span id="primary-level"><?= e(is_numeric($primary['water_level_gauge_m'] ?? null) ? number_format((float) $primary['water_level_gauge_m'], 2) : '—') ?></span><small>m</small></div>
    <p class="muted"><?= e(t('home.level')) ?> · <span id="primary-time"><?= e(format_local_time($primary['measured_at'] ?? null)) ?></span></p>
    <div class="metric-grid three">
        <div><span><?= e(t('home.change')) ?></span><strong id="primary-trend"><?= e(trend_display($primary['change_1h_m'] ?? null)) ?></strong></div>
        <div><span><?= e(t('home.msl')) ?></span><strong id="primary-msl"><?= e(format_level($primary['water_level_msl_m'] ?? null)) ?></strong></div>
        <div><span id="primary-reference-label" data-warning-label="<?= e(t('home.warning_level')) ?>" data-position-label="<?= e(t('station.position')) ?>"><?= e(t('home.warning_level')) ?></span><strong id="primary-reference-value" data-warning-value="<?= e(format_level($warningThreshold)) ?>"><?= e(format_level($warningThreshold)) ?></strong></div>
    </div>
    <p class="selection-note"><?= e(t('home.station_picker_help')) ?></p>
</section>

<section id="river-risk" class="risk-card severity-<?= e($river['severity']) ?>" aria-labelledby="river-risk-title">
    <div class="risk-icon" aria-hidden="true"><span class="material-symbols">waves</span></div>
    <div><p class="kicker"><?= e(t('home.river_risk')) ?></p><h2 id="river-risk-title"><?= e(t('severity.' . $river['severity'])) ?></h2><p id="river-message"><?= e(t($river['message_key'])) ?></p></div>
</section>

<section class="card chart-card" aria-labelledby="chart-title">
    <div class="card-heading"><h2 id="chart-title"><?= e(t('home.chart_title')) ?></h2><span><?= e(t('common.last_24h')) ?></span></div>
    <div class="chart-wrap"><canvas id="water-chart" aria-label="<?= e(t('home.chart_title')) ?>" role="img"></canvas><p id="chart-empty" class="empty" hidden><?= e(t('home.chart_empty')) ?></p></div>
    <p id="chart-status" class="inline-status" hidden><?= e(t('common.chart_failed')) ?></p>
</section>

<section class="card" aria-labelledby="upstream-title">
    <div class="card-heading"><h2 id="upstream-title"><?= e(t('home.station_status')) ?></h2><a href="<?= e(url('stations.php?lang=' . locale())) ?>"><?= e(t('common.details')) ?></a></div>
    <div id="station-list" class="station-rows">
    <?php foreach ($dashboard['stations'] as $station): if ((int) $station['is_primary'] === 1 || ($station['river_role'] ?? 'upstream') !== 'upstream') continue; ?>
        <a href="<?= e(url('station.php?code=' . rawurlencode($station['provider_station_code']) . '&lang=' . locale())) ?>" class="station-row" data-station-code="<?= e($station['provider_station_code']) ?>">
            <span><strong><?= e($station['provider_station_code']) ?></strong><small><?= e(locale() === 'th' ? $station['display_name_th'] : $station['display_name_en']) ?></small></span>
            <span class="station-value"><strong data-field="gauge"><?= e(format_level($station['water_level_gauge_m'])) ?></strong><small data-field="trend"><?= e(trend_display($station['change_1h_m'])) ?> / 1h</small><small data-field="time"><?= e(format_local_time($station['measured_at'] ?? null)) ?></small><small data-field="freshness" class="badge badge-inline badge-<?= e($station['freshness_status'] ?? 'offline') ?>"><?= e(t('freshness.' . ($station['freshness_status'] ?? 'offline'))) ?></small><small data-field="rapid-rise" class="rapid-rise" hidden><?= e(t('home.rising_fast')) ?></small></span>
        </a>
    <?php endforeach; ?>
    </div>
</section>

</div>
<aside class="dashboard-aside">
<section id="weather-risk" class="risk-card severity-<?= e($weather['severity']) ?>" aria-labelledby="weather-risk-title">
    <div class="risk-icon" aria-hidden="true"><span class="material-symbols">rainy</span></div>
    <div><p class="kicker"><?= e(t('home.weather_risk')) ?></p><h2 id="weather-risk-title"><?= e(t('severity.' . $weather['severity'])) ?></h2><p id="weather-message"><?= e(t($weather['message_key'])) ?></p></div>
</section>

<section class="card weather-card" aria-labelledby="weather-title">
    <div class="card-heading"><div><p class="kicker"><?= e(t('common.next_24h')) ?></p><h2 id="weather-title"><?= e(t('home.weather_title')) ?></h2></div><span class="material-symbols weather-symbol" aria-hidden="true">rainy</span></div>
    <div class="metric-grid three weather-metrics">
        <div><span><?= e(t('home.rain_24h')) ?></span><strong id="rain-range"><?php
            $min = $weatherContext['rain_24h_min_mm'] ?? null; $max = $weatherContext['rain_24h_max_mm'] ?? null;
            echo e(is_numeric($min) && is_numeric($max) ? number_format((float) $min, 1) . '–' . number_format((float) $max, 1) . ' mm' : '—');
        ?></strong></div>
        <div><span><?= e(t('home.hourly_peak')) ?></span><strong id="rain-peak"><?= e(format_rain($weatherContext['max_hourly_rain_mm'] ?? null)) ?></strong></div>
        <div><span><?= e(t('home.probability')) ?></span><strong id="rain-probability"><?= e(is_numeric($weatherContext['max_probability_pct'] ?? null) ? number_format((float) $weatherContext['max_probability_pct'], 0) . '%' : '—') ?></strong></div>
    </div>
    <div class="forecast-horizons" aria-label="<?= e(t('a11y.rain_horizons')) ?>">
        <?php foreach ([6, 12, 24, 48] as $hours):
            $hMin = $weatherContext['rain_' . $hours . 'h_min_mm'] ?? null;
            $hMax = $weatherContext['rain_' . $hours . 'h_max_mm'] ?? null; ?>
        <div><span><?= e((string) $hours) ?> h</span><strong id="rain-<?= e((string) $hours) ?>h"><?= e(is_numeric($hMin) && is_numeric($hMax) ? number_format((float) $hMin, 1) . '–' . number_format((float) $hMax, 1) . ' mm' : '—') ?></strong></div>
        <?php endforeach; ?>
    </div>
    <p class="range-note"><?= e(t('home.range_note')) ?></p>
    <p class="range-note" id="forecast-updated" data-template="<?= e(t('home.forecast_updated', ['time' => '{time}'])) ?>"><?= e(t('home.forecast_updated', ['time' => format_local_time($weatherContext['forecast_received_at'] ?? null)])) ?></p>
    <p class="attribution"><a href="https://open-meteo.com/" rel="external noopener"><?= e(t('home.attribution')) ?></a></p>
</section>

<section id="combined-risk" class="advisory severity-<?= e($combined['severity']) ?>" aria-labelledby="advisory-title">
    <span class="material-symbols" aria-hidden="true">campaign</span>
    <div><p class="kicker"><?= e(t('home.advisory')) ?></p><h2 id="advisory-title"><?= e(t('severity.' . $combined['severity'])) ?></h2><p id="combined-message"><?= e(t($combined['message_key'])) ?></p></div>
</section>
</aside>
</div>

<p class="updated"><span id="dashboard-updated" data-template="<?= e(t('common.updated', ['time' => '{time}'])) ?>"><?= e(t('common.updated', ['time' => format_local_time($dashboard['generated_at'])])) ?></span></p>
