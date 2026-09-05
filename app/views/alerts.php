<?php
$pushEnabled = (bool) \PingFloodWatch\Config::get('push.enabled', false);
$vapidPublicKey = $pushEnabled ? (string) \PingFloodWatch\Config::get('push.public_key', '') : '';
?>
<section class="page-heading"><h1><?= e(t('alerts.title')) ?></h1><p><?= e(t('alerts.subtitle')) ?></p></section>

<section id="push-settings" class="card push-settings"
         data-enabled="<?= $pushEnabled && $vapidPublicKey !== '' ? 'true' : 'false' ?>"
         data-vapid-public-key="<?= e($vapidPublicKey) ?>"
         data-label-enable="<?= e(t('push.enable')) ?>"
         data-label-disable="<?= e(t('push.disable')) ?>"
         data-status-not-requested="<?= e(t('push.not_requested')) ?>"
         data-status-enabled="<?= e(t('push.enabled')) ?>"
         data-status-denied="<?= e(t('push.denied')) ?>"
         data-status-unsupported="<?= e(t('push.unsupported')) ?>"
         data-status-install-required="<?= e(t('push.install_required')) ?>"
         data-status-error="<?= e(t('push.error')) ?>">
    <div class="card-heading"><div><p class="kicker"><?= e(t('push.setting_title')) ?></p><h2><?= e(t('push.setting_title')) ?></h2></div><span class="material-symbols push-symbol" aria-hidden="true">notifications_active</span></div>
    <p><?= e(t('push.setting_body')) ?></p>
    <p id="push-status" class="push-status" role="status"></p>
    <button id="push-toggle" class="primary-button" type="button"><?= e(t('push.enable')) ?></button>
</section>

<?php if ($loadError): ?><div class="notice notice-unknown" role="alert"><strong><?= e(t('error.title')) ?></strong><span><?= e(t('error.body')) ?></span></div><?php endif; ?>
<div id="alerts-empty" class="empty-state"<?= $alerts || $loadError ? ' hidden' : '' ?>><span class="material-symbols" aria-hidden="true">notifications_none</span><p><?= e(t('alerts.none')) ?></p></div>
<div id="alert-list" class="alert-list" aria-live="polite"
     data-triggered-template="<?= e(t('alerts.triggered', ['time' => '{time}'])) ?>"
     data-pending-text="<?= e(t('alerts.pending')) ?>">
<?php foreach ($alerts as $alert): ?>
<article class="card alert-item severity-<?= e($alert['severity']) ?>" data-alert-id="<?= e((string) $alert['id']) ?>">
    <div class="card-heading"><h2><?= e(t($alert['title_key'])) ?></h2><span class="badge badge-<?= e($alert['status']) ?>"><?= e(t('alerts.' . $alert['status'])) ?></span></div>
    <p><?= e(t($alert['message_key'])) ?></p>
    <p class="muted"><?= e(t('alerts.triggered', ['time' => format_local_time($alert['triggered_at'])])) ?></p>
    <?php if ($alert['pending_since']): ?><p class="pending-note"><?= e(t('alerts.pending')) ?></p><?php endif; ?>
</article>
<?php endforeach; ?>
</div>
