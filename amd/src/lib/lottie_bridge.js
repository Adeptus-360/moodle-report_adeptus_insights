// amd/src/lib/lottie_bridge.js
// Lottie library is loaded globally via PHP $PAGE->requires->js()
// This bridge module provides AMD access to the global lottie object
define([], function() {
  return window.lottie || window.bodymovin || null;
});

