'use strict';

(() => {
  const P = window.PFW;
  const list = document.getElementById('alert-list');
  const empty = document.getElementById('alerts-empty');
  if (!list) return;
  let signature = '';

  const render = alerts => {
    const nextSignature = JSON.stringify(alerts);
    if (nextSignature === signature) return;
    signature = nextSignature;
    const fragment = document.createDocumentFragment();
    alerts.forEach(alert => {
      const article = document.createElement('article');
      article.className = `card alert-item severity-${alert.severity}`;
      article.dataset.alertId = String(alert.id);
      const heading = document.createElement('div');
      heading.className = 'card-heading';
      const title = document.createElement('h2');
      title.textContent = alert.title;
      const badge = document.createElement('span');
      badge.className = `badge badge-${alert.status}`;
      badge.textContent = alert.status_label;
      heading.append(title, badge);
      const message = document.createElement('p');
      message.textContent = alert.message;
      const triggered = document.createElement('p');
      triggered.className = 'muted';
      triggered.textContent = list.dataset.triggeredTemplate.replace('{time}', P.localTime(alert.triggered_at));
      article.append(heading, message, triggered);
      if (alert.pending_since) {
        const pending = document.createElement('p');
        pending.className = 'pending-note';
        pending.textContent = list.dataset.pendingText;
        article.append(pending);
      }
      fragment.append(article);
    });
    list.replaceChildren(fragment);
    if (empty) empty.hidden = alerts.length > 0;
  };

  const refresh = async () => {
    try {
      const response = await fetch(`${P.base}api/alerts.php?lang=${encodeURIComponent(P.language)}`, {cache: 'no-store'});
      const payload = await response.json();
      if (!response.ok || !payload.ok) throw new Error('ALERTS_API_FAILED');
      render(payload.data.alerts || []);
      P.setApiFailure('alerts', false);
      return true;
    } catch (_) {
      P.setApiFailure('alerts', true);
      return false;
    }
  };

  P.createRefreshTask(refresh, {intervalMs: 60000, resumeThresholdMs: 60000}).run(true);
})();
