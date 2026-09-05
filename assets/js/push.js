'use strict';

(() => {
  const P = window.PFW;
  const panel = document.getElementById('push-settings');
  const button = document.getElementById('push-toggle');
  const status = document.getElementById('push-status');
  if (!panel || !button || !status) return;

  const isIos = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  const supported = panel.dataset.enabled === 'true' && window.isSecureContext && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
  let subscription = null;
  let busy = false;

  const setState = (state, message, action = null) => {
    panel.dataset.state = state;
    status.textContent = message;
    button.hidden = action === null;
    if (action !== null) {
      button.disabled = busy;
      button.textContent = action === 'disable' ? panel.dataset.labelDisable : panel.dataset.labelEnable;
      button.dataset.action = action;
    }
  };
  const urlBase64ToUint8Array = value => {
    const padded = `${value}${'='.repeat((4 - value.length % 4) % 4)}`;
    const raw = atob(padded.replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from([...raw].map(character => character.charCodeAt(0)));
  };
  const post = async (path, body) => {
    const response = await fetch(`${P.base}api/${path}`, {
      method: 'POST', credentials: 'same-origin', cache: 'no-store',
      headers: {'Content-Type': 'application/json'}, body: JSON.stringify(body)
    });
    const payload = await response.json();
    if (!response.ok || !payload.ok) throw new Error(payload.error?.code || 'PUSH_API_FAILED');
    return payload;
  };
  const showCurrentState = () => {
    if (!supported) return setState('unsupported', panel.dataset.statusUnsupported);
    if (isIos && !standalone) return setState('install_required', panel.dataset.statusInstallRequired);
    if (Notification.permission === 'denied') return setState('denied', panel.dataset.statusDenied);
    if (subscription) return setState('subscribed', panel.dataset.statusEnabled, 'disable');
    return setState('not_requested', panel.dataset.statusNotRequested, 'enable');
  };
  const subscriptionBody = value => {
    const json = value.toJSON();
    return {
      endpoint: json.endpoint,
      keys: {p256dh: json.keys?.p256dh, auth: json.keys?.auth},
      language: P.language,
      contentEncoding: PushManager.supportedContentEncodings?.includes('aes128gcm') ? 'aes128gcm' : 'aesgcm'
    };
  };

  const initialize = async () => {
    if (!supported || (isIos && !standalone)) return showCurrentState();
    if (Notification.permission === 'denied') return showCurrentState();
    try {
      const registration = await navigator.serviceWorker.ready;
      subscription = await registration.pushManager.getSubscription();
    } catch (_) {
      return setState('error', panel.dataset.statusError, 'enable');
    }
    showCurrentState();
  };

  button.addEventListener('click', async () => {
    if (busy || !supported) return;
    busy = true;
    button.disabled = true;
    try {
      if (button.dataset.action === 'disable' && subscription) {
        await post('push-unsubscribe.php', {endpoint: subscription.endpoint});
        await subscription.unsubscribe();
        subscription = null;
      } else {
        if (Notification.permission === 'default') {
          const permission = await Notification.requestPermission();
          if (permission !== 'granted') return showCurrentState();
        }
        if (Notification.permission !== 'granted') return showCurrentState();
        const registration = await navigator.serviceWorker.ready;
        subscription = await registration.pushManager.getSubscription();
        if (!subscription) {
          subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(panel.dataset.vapidPublicKey)
          });
        }
        try {
          await post('push-subscribe.php', subscriptionBody(subscription));
        } catch (error) {
          await subscription.unsubscribe().catch(() => {});
          subscription = null;
          throw error;
        }
      }
      showCurrentState();
    } catch (_) {
      setState('error', panel.dataset.statusError, subscription ? 'disable' : 'enable');
    } finally {
      busy = false;
      button.disabled = false;
    }
  });

  initialize();
})();
