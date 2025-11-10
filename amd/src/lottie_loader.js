define(['report_adeptus_insights/lib/lottie_bridge'], function(lottie) {
  function url(raw) {
    if (!raw) return null;
    if (/^https?:\/\//i.test(raw)) return raw;               // absolute
    if (raw[0] === '/') return window.location.origin + raw; // root-relative
    return M.cfg.wwwroot + '/' + raw.replace(/^\/+/, '');    // plugin-relative
  }

  function init() {
    var hosts = document.querySelectorAll('[data-lottie-path]');
    if (!hosts.length) return;

    if (!lottie || typeof lottie.loadAnimation !== 'function') {
      console.error('[Lottie Loader] Lottie library not available');
                return;
            }
            
    hosts.forEach(function(el) {
      var p = url(el.getAttribute('data-lottie-path'));
      if (!p) return;
      try {
        lottie.loadAnimation({
          container: el,
                    renderer: 'svg',
          loop: true,
          autoplay: true,
          path: p
        });
      } catch (e) {
        console.error('[Lottie Loader] Failed for', p, e);
                }
            });
        }

  return { init: init };
});