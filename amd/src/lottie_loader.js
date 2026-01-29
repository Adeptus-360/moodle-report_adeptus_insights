// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Lottie animation loader for Adeptus Insights plugin.
 *
 * Initializes and loads Lottie animations for elements with data-lottie-path
 * attributes, handling URL resolution for absolute, root-relative, and
 * plugin-relative paths.
 *
 * @module     report_adeptus_insights/lottie_loader
 * @package    report_adeptus_insights
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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
        // Animation load failed silently.
                }
            });
        }

  return { init: init };
});