// amd/src/lib/lottie_bridge.js
define(['report_adeptus_insights/animation_runtime'], function(runtime) {
  // runtime: what the UMD returns when AMD is detected (often the API)
  // window.lottie/bodymovin: what older/bundled builds attach globally
  var api = runtime || window.lottie || window.bodymovin || null;
  return api;
});

