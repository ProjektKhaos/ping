<section class="page-heading">
    <a class="back-link" href="<?= e(url('stations.php?lang=' . locale())) ?>"><span class="material-symbols" aria-hidden="true">arrow_back</span><?= e(t('nav.stations')) ?></a>
    <h1><?= e(t('station.history', ['code' => $stationCode])) ?></h1>
    <?php if ($station): ?><p><?= e($station['display_name_' . locale()]) ?> · <?= e($station['river_name_' . locale()]) ?></p><?php endif; ?>
</section>
<?php if ($loadError): ?><div class="notice notice-unknown" role="alert"><strong><?= e(t('error.title')) ?></strong><span><?= e(t('error.body')) ?></span></div><?php endif; ?>
<?php if ($station): ?>
<section class="card detail-summary">
    <div class="card-heading"><span id="station-freshness" class="badge badge-<?= e($station['freshness_status']) ?>"><?= e(t('freshness.' . $station['freshness_status'])) ?></span><span id="station-time"><?= e(format_local_time($station['measured_at'])) ?></span></div>
    <div class="level-reading"><span id="station-level"><?= e(is_numeric($station['water_level_gauge_m']) ? number_format((float) $station['water_level_gauge_m'], 2) : '—') ?></span><small>m</small></div>
    <p class="muted"><?= e(t('station.gauge')) ?></p>
    <button id="detail-home-button" class="secondary-button detail-home-button" type="button" data-code="<?= e($stationCode) ?>" data-set-label="<?= e(t('station.set_home')) ?>" data-home-label="<?= e(t('station.home')) ?>" aria-pressed="false">
        <span class="material-symbols" aria-hidden="true">home</span><span data-label><?= e(t('station.set_home')) ?></span>
    </button>
    <div class="metric-grid two">
        <div><span><?= e(t('home.msl')) ?></span><strong id="station-msl"><?= e(format_level($station['water_level_msl_m'])) ?></strong></div>
        <div><span><?= e(t('station.capacity')) ?></span><strong id="station-capacity"><?= e(is_numeric($station['capacity_percent']) ? number_format((float) $station['capacity_percent'], 0) . '%' : '—') ?></strong></div>
    </div>
    <div class="trend-grid" aria-label="<?= e(t('station.trends')) ?>">
        <?php foreach ([1, 3, 6, 12, 24] as $hours): ?>
        <div><span><?= e((string) $hours) ?> h</span><strong id="station-trend-<?= e((string) $hours) ?>h"><?= e(trend_display($station['change_' . $hours . 'h_m'])) ?></strong></div>
        <?php endforeach; ?>
    </div>
</section>
<section class="card chart-card">
    <div class="card-heading"><h2><?= e(t('home.chart_title')) ?></h2><label class="select-label"><span class="sr-only"><?= e(t('a11y.history_period')) ?></span><select id="history-period"><option value="24h">24 h</option><option value="48h">48 h</option><option value="72h" selected>72 h</option></select></label></div>
    <div class="chart-wrap tall"><canvas id="station-chart" data-station="<?= e($stationCode) ?>" aria-label="<?= e(t('home.chart_title')) ?>" role="img"></canvas><p id="chart-empty" class="empty" hidden><?= e(t('home.chart_empty')) ?></p></div>
    <p id="chart-status" class="inline-status" hidden><?= e(t('common.chart_failed')) ?></p>
</section>
<?php if ($station['thresholds']): ?>
<section class="card"><h2><?= e(t('station.thresholds')) ?></h2><div class="threshold-list">
<?php foreach ($station['thresholds'] as $type => $threshold): ?><div><span><?= e(t('threshold.' . $type)) ?></span><strong><?= e(format_level($threshold['value_m'])) ?></strong><a href="<?= e($threshold['source_url']) ?>" rel="external noopener"><?= e($threshold['source_name']) ?></a></div><?php endforeach; ?>
</div></section>
<?php endif; ?>
<p class="datum-note"><span class="material-symbols" aria-hidden="true">info</span><?= e(t('station.datum_note')) ?></p>
<?php endif; ?>
