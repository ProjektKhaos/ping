<?php
$mapStations = array_values(array_filter(
    $stations,
    static fn (array $station): bool => is_numeric($station['latitude'] ?? null) && is_numeric($station['longitude'] ?? null)
));
$initialMapStation = null;
foreach ($mapStations as $mapStation) {
    if ((int) ($mapStation['is_primary'] ?? 0) === 1) {
        $initialMapStation = $mapStation;
        break;
    }
}
$initialMapStation ??= $mapStations[0] ?? null;
?>
<section class="page-heading"><h1><?= e(t('station.title')) ?></h1><p><?= e(t('station.subtitle')) ?></p></section>
<?php if ($loadError): ?><div class="notice notice-unknown" role="alert"><strong><?= e(t('error.title')) ?></strong><span><?= e(t('error.body')) ?></span></div><?php endif; ?>

<?php if ($stations !== []): ?>
<div class="station-view-toggle" role="group" aria-label="<?= e(t('station.view_label')) ?>">
    <button type="button" data-station-view="list" aria-pressed="true"><span class="material-symbols" aria-hidden="true">format_list_bulleted</span><?= e(t('station.view_list')) ?></button>
    <button type="button" data-station-view="map" aria-pressed="false"><span class="material-symbols" aria-hidden="true">map</span><?= e(t('station.view_map')) ?></button>
</div>

<section id="stations-map-panel" class="station-view" data-view-panel="map" hidden aria-labelledby="station-map-title">
    <div class="card station-map-card">
        <div class="card-heading"><div><p class="kicker"><?= e(t('station.north')) ?></p><h2 id="station-map-title"><?= e(t('station.map_title')) ?></h2></div><span class="material-symbols map-heading-icon" aria-hidden="true">location_on</span></div>
        <p class="map-intro"><?= e(t('station.map_subtitle')) ?></p>
        <div id="station-map" class="station-map leaflet-station-map" role="application"
             aria-label="<?= e(t('station.map_title')) ?>"
             data-tile-url="<?= e((string) \PingFloodWatch\Config::get('maps.tile_url')) ?>"
             data-attribution-url="<?= e((string) \PingFloodWatch\Config::get('maps.attribution_url')) ?>"
             data-max-zoom="<?= e((string) \PingFloodWatch\Config::get('maps.max_zoom', 18)) ?>"
             data-details-label="<?= e(t('common.details')) ?>"
             data-tile-error="<?= e(t('station.map_tiles_unavailable')) ?>"></div>
        <div id="map-station-data" hidden>
        <?php foreach ($mapStations as $station):
            $code = (string) $station['provider_station_code'];
            $role = (string) ($station['river_role'] ?? ((int) $station['is_primary'] === 1 ? 'primary' : 'upstream'));
            $detailUrl = url('station.php?code=' . rawurlencode($code) . '&lang=' . locale());
        ?>
            <span data-map-station="<?= e($code) ?>" data-code="<?= e($code) ?>"
                  data-name="<?= e($station['display_name_' . locale()]) ?>"
                  data-role="<?= e($role) ?>" data-role-label="<?= e(t('station.role.' . $role)) ?>"
                  data-latitude="<?= e((string) $station['latitude']) ?>" data-longitude="<?= e((string) $station['longitude']) ?>"
                  data-gauge="<?= e(is_numeric($station['water_level_gauge_m'] ?? null) ? number_format((float) $station['water_level_gauge_m'], 2, '.', '') : '') ?>"
                  data-trend="<?= e(trend_display($station['change_1h_m'] ?? null)) ?>"
                  data-time="<?= e(format_local_time($station['measured_at'] ?? null)) ?>"
                  data-freshness="<?= e((string) ($station['freshness_status'] ?? 'offline')) ?>"
                  data-freshness-label="<?= e(t('freshness.' . ($station['freshness_status'] ?? 'offline'))) ?>"
                  data-detail-url="<?= e($detailUrl) ?>"
                  data-select-label="<?= e(t('station.map_select', ['code' => $code, 'name' => $station['display_name_' . locale()]])) ?>"></span>
        <?php endforeach; ?>
        </div>
        <p class="map-note"><span class="material-symbols" aria-hidden="true">info</span><?= e(t('station.map_note')) ?></p>
    </div>

    <?php if ($initialMapStation):
        $initialCode = (string) $initialMapStation['provider_station_code'];
        $initialRole = (string) ($initialMapStation['river_role'] ?? 'primary');
    ?>
    <article id="map-station-summary" class="card map-station-summary" aria-live="polite">
        <div class="card-heading">
            <div><p id="map-summary-role" class="kicker"><?= e(t('station.role.' . $initialRole)) ?></p><h2 id="map-summary-title"><?= e($initialCode . ' · ' . $initialMapStation['display_name_' . locale()]) ?></h2></div>
            <span id="map-summary-freshness" class="badge badge-<?= e((string) ($initialMapStation['freshness_status'] ?? 'offline')) ?>"><?= e(t('freshness.' . ($initialMapStation['freshness_status'] ?? 'offline'))) ?></span>
        </div>
        <div class="level-reading compact"><span id="map-summary-gauge"><?= e(is_numeric($initialMapStation['water_level_gauge_m'] ?? null) ? number_format((float) $initialMapStation['water_level_gauge_m'], 2) : '—') ?></span><small>m</small></div>
        <div class="metric-grid two">
            <div><span><?= e(t('home.change')) ?></span><strong id="map-summary-trend"><?= e(trend_display($initialMapStation['change_1h_m'] ?? null)) ?></strong></div>
            <div><span><?= e(t('station.measured')) ?></span><strong id="map-summary-time"><?= e(format_local_time($initialMapStation['measured_at'] ?? null)) ?></strong></div>
        </div>
        <p id="map-summary-coordinates" class="map-coordinates"><?= e(number_format((float) $initialMapStation['latitude'], 5) . ', ' . number_format((float) $initialMapStation['longitude'], 5)) ?></p>
        <div class="station-actions">
            <button id="map-home-station-button" class="secondary-button home-station-button" type="button" data-code="<?= e($initialCode) ?>" data-set-label="<?= e(t('station.set_home')) ?>" data-home-label="<?= e(t('station.home')) ?>" aria-pressed="false">
                <span class="material-symbols" aria-hidden="true">home</span><span data-label><?= e(t('station.set_home')) ?></span>
            </button>
            <a id="map-summary-link" class="button-link" href="<?= e(url('station.php?code=' . rawurlencode($initialCode) . '&lang=' . locale())) ?>"><?= e(t('common.details')) ?><span class="material-symbols" aria-hidden="true">chevron_right</span></a>
        </div>
    </article>
    <?php endif; ?>
</section>

<section id="stations-list-panel" class="station-view" data-view-panel="list">
    <div class="river-end"><span><?= e(t('station.north')) ?></span></div>
    <div id="stations-grid" class="stations-grid">
    <?php foreach ($stations as $index => $station):
        $code = $station['provider_station_code'];
        $role = $station['river_role'] ?? ((int) $station['is_primary'] === 1 ? 'primary' : 'upstream');
    ?>
    <article class="card station-card" data-station-code="<?= e($station['provider_station_code']) ?>">
        <div class="card-heading">
            <div><p class="kicker"><?= e($station['river_name_' . locale()]) ?></p><h2><?= e($station['provider_station_code']) ?> · <?= e($station['display_name_' . locale()]) ?></h2></div>
            <div class="station-badges">
                <span class="role-badge"><?= e(t('station.role.' . $role)) ?></span>
                <span data-field="freshness" class="badge badge-<?= e($station['freshness_status'] ?? 'offline') ?>"><?= e(t('freshness.' . ($station['freshness_status'] ?? 'offline'))) ?></span>
            </div>
        </div>
        <div class="level-reading compact"><span data-field="gauge"><?= e(is_numeric($station['water_level_gauge_m']) ? number_format((float) $station['water_level_gauge_m'], 2) : '—') ?></span><small>m</small></div>
        <div class="metric-grid two">
            <div><span><?= e(t('home.change')) ?></span><strong data-field="trend"><?= e(trend_display($station['change_1h_m'])) ?></strong></div>
            <div><span><?= e(t('station.measured')) ?></span><strong data-field="time"><?= e(format_local_time($station['measured_at'])) ?></strong></div>
        </div>
        <div class="station-actions">
            <button class="secondary-button home-station-button" type="button" data-code="<?= e($code) ?>" data-set-label="<?= e(t('station.set_home')) ?>" data-home-label="<?= e(t('station.home')) ?>" aria-pressed="false">
                <span class="material-symbols" aria-hidden="true">home</span><span data-label><?= e(t('station.set_home')) ?></span>
            </button>
            <a class="button-link" href="<?= e(url('station.php?code=' . rawurlencode($code) . '&lang=' . locale())) ?>" aria-label="<?= e(t('common.details') . ' ' . $code) ?>"><?= e(t('common.details')) ?><span class="material-symbols" aria-hidden="true">chevron_right</span></a>
        </div>
    </article>
    <?php if ($index < count($stations) - 1): ?><div class="river-arrow" aria-hidden="true"><span class="material-symbols">south</span></div><?php endif; ?>
    <?php endforeach; ?>
    </div>
    <div class="river-end"><span><?= e(t('station.south')) ?></span></div>
</section>
<p class="selection-note station-selection-help"><?= e(t('station.home_help')) ?></p>
<p class="datum-note"><span class="material-symbols" aria-hidden="true">info</span><?= e(t('station.datum_note')) ?></p>
<?php endif; ?>
