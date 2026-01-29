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
 * Lottie bridge module - loads Lottie library dynamically.
 *
 * This module loads Lottie from CDN while avoiding RequireJS AMD conflicts
 * by temporarily hiding the define function during script load.
 *
 * @module     report_adeptus_insights/lib/lottie_bridge
 * @package    report_adeptus_insights
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    'use strict';

    // Return existing lottie if already loaded.
    if (window.lottie) {
        return window.lottie;
    }

    // Create a promise that resolves when Lottie is loaded.
    var lottiePromise = new Promise(function(resolve) {
        // Check if already loaded.
        if (window.lottie) {
            resolve(window.lottie);
            return;
        }

        // Create script element.
        var script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.2/lottie.min.js';
        script.async = true;

        // Temporarily hide define to prevent AMD registration.
        var originalDefine = window.define;
        window.define = undefined;

        script.onload = function() {
            // Restore define.
            window.define = originalDefine;
            resolve(window.lottie || window.bodymovin || null);
        };

        script.onerror = function() {
            // Restore define on error.
            window.define = originalDefine;
            resolve(null);
        };

        document.head.appendChild(script);
    });

    // Return an object that proxies to the loaded lottie.
    // This allows the module to be used synchronously while lottie loads.
    var proxy = {
        _ready: false,
        _lottie: null,
        _queue: [],

        loadAnimation: function(config) {
            var self = this;
            if (self._ready && self._lottie) {
                return self._lottie.loadAnimation(config);
            }
            // Queue the animation load until lottie is ready.
            lottiePromise.then(function(lottie) {
                self._ready = true;
                self._lottie = lottie;
                if (lottie && typeof lottie.loadAnimation === 'function') {
                    lottie.loadAnimation(config);
                }
            });
            return null;
        }
    };

    // Initialize when lottie loads.
    lottiePromise.then(function(lottie) {
        proxy._ready = true;
        proxy._lottie = lottie;
    });

    return proxy;
});
