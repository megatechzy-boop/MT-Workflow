(function () {
  if (window.__mtOneSignalLoaded) return;
  window.__mtOneSignalLoaded = true;
  const appId = '38fbfa89-0e2b-4692-9675-5b07ccaebe4e';
  window.OneSignalDeferred = window.OneSignalDeferred || [];
  window.OneSignalDeferred.push(async function (OneSignal) {
    const serviceWorkerPath = new URL('.', window.location.href).pathname;
    await OneSignal.init({ appId, allowLocalhostAsSecureOrigin: true, serviceWorkerPath: serviceWorkerPath + 'OneSignalSDKWorker.js', serviceWorkerParam: { scope: serviceWorkerPath } });
    const response = await fetch('push_register.php', { credentials: 'same-origin' });
    const user = await response.json();
    if (!user.authenticated) return;
    await OneSignal.login(String(user.user_id));
    if (OneSignal.Notifications.permission !== 'granted') await OneSignal.Notifications.requestPermission();
    const save = async function (subscription) {
      if (!subscription || !subscription.id) return;
      await fetch('push_register.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ subscription_id: subscription.id, csrf_token: user.csrf_token }) });
    };
    await save(OneSignal.User.PushSubscription);
    OneSignal.User.PushSubscription.addEventListener('change', function (event) { save(event.current); });
  });
  const script = document.createElement('script'); script.src = 'https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js'; script.defer = true; document.head.appendChild(script);
}());
