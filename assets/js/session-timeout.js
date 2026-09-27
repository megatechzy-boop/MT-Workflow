(function () {
  var oneSignalScript = document.createElement('script'); oneSignalScript.src = 'assets/js/onesignal.js?v=1'; document.head.appendChild(oneSignalScript);
  var navScript = document.createElement('script'); navScript.src = 'assets/js/responsive-nav.js?v=2'; document.head.appendChild(navScript);
  const idleLimit = 30 * 60 * 1000;
  let timer;
  function reset() {
    clearTimeout(timer);
    timer = setTimeout(function () {
      window.location.href = 'logout.php?reason=timeout';
    }, idleLimit);
  }
  ['click', 'keydown', 'mousemove', 'scroll', 'touchstart'].forEach(function (event) {
    window.addEventListener(event, reset, { passive: true });
  });
  reset();
}());
