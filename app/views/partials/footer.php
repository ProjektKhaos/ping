    </main>
    <footer class="page-footer">
        <p><?= e(t('common.disclaimer')) ?></p>
        <p><a href="https://open-meteo.com/" rel="external noopener"><?= e(t('home.attribution')) ?></a> · <a href="https://www.thaiwater.net/" rel="external noopener">ThaiWater</a> · <a href="https://watercenter.cmu.ac.th/chiangmai/cmflood/warning/station" rel="external noopener">CMFlood / CMU</a></p>
        <p class="developer-credit">Developed by Hans Åberg</p>
    </footer>
    <nav class="bottom-nav" aria-label="<?= e(t('a11y.primary_nav')) ?>">
        <a class="nav-item nav-home" href="<?= e(url('?lang=' . $lang)) ?>"<?= $activeNav === 'home' ? ' aria-current="page"' : '' ?>><span class="nav-icon"><span class="material-symbols" aria-hidden="true">home</span></span><span class="nav-label"><?= e(t('nav.home')) ?></span></a>
        <a class="nav-item nav-stations" href="<?= e(url('stations.php?lang=' . $lang)) ?>"<?= $activeNav === 'stations' ? ' aria-current="page"' : '' ?>><span class="nav-icon"><span class="material-symbols" aria-hidden="true">waves</span></span><span class="nav-label"><?= e(t('nav.stations')) ?></span></a>
        <a class="nav-item nav-alerts" href="<?= e(url('alerts.php?lang=' . $lang)) ?>"<?= $activeNav === 'alerts' ? ' aria-current="page"' : '' ?>><span class="nav-icon"><span class="material-symbols" aria-hidden="true">notifications_active</span></span><span class="nav-label"><?= e(t('nav.alerts')) ?></span></a>
    </nav>
</div>
<script src="<?= e(asset_url('assets/js/app.js')) ?>" defer></script>
<?php if (!empty($pageScripts) && is_array($pageScripts)): foreach ($pageScripts as $script): ?>
<script src="<?= e(asset_url($script)) ?>" defer></script>
<?php endforeach; endif; ?>
</body>
</html>
