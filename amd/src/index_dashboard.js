/**
 * Dashboard Index JavaScript Module
 *
 * Handles counter animations for subscription stats using IntersectionObserver.
 *
 * @module     report_adeptus_insights/index_dashboard
 * @package    report_adeptus_insights
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {
    'use strict';

    /**
     * Check if user prefers reduced motion.
     * @type {boolean}
     */
    var prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /**
     * Format large numbers in compact form (e.g., 1.5k, 2.3M).
     * @param {number} n - The number to format
     * @returns {string} Formatted number string
     */
    var numberFormatter = function(n) {
        var v = +n;
        if (!isFinite(v)) {
            return String(n);
        }
        // Compact for big numbers
        if (v >= 1000000) {
            return (Math.round(v / 100000) / 10) + 'M';
        }
        if (v >= 1000) {
            return (Math.round(v / 100) / 10) + 'k';
        }
        return v.toString();
    };

    /**
     * Animate a counter element from 0 to target value.
     * @param {HTMLElement} el - The element to animate
     * @param {string|number} to - Target value
     * @param {Object} opts - Animation options
     * @param {number} opts.duration - Animation duration in ms
     * @param {Function} opts.format - Number formatting function
     */
    var animateCount = function(el, to, opts) {
        var dur = (opts && opts.duration) || 1400;
        var fmt = (opts && opts.format) || function(v) {
            return v.toString();
        };
        var start = 0;
        var end = +to; // Coerce to number

        if (!isFinite(end)) {
            // Non-numeric target: show raw text and stop
            el.textContent = (to === null || typeof to === 'undefined') ? '-' : String(to);
            el.classList.add('no-animate');
            return;
        }

        if (prefersReduced) {
            el.textContent = fmt(end);
            return;
        }

        var t0 = null;

        var step = function(ts) {
            if (!t0) {
                t0 = ts;
            }
            var p = Math.min(1, (ts - t0) / dur);
            // easeOutCubic
            var eased = 1 - Math.pow(1 - p, 3);
            var current = Math.round(start + (end - start) * eased);
            el.textContent = fmt(current);

            if (p < 1) {
                requestAnimationFrame(step);
            } else {
                el.textContent = fmt(end);
                el.classList.add('counted');
            }
        };

        requestAnimationFrame(step);
    };

    /**
     * Initialize counters with IntersectionObserver.
     */
    var bootCounters = function() {
        var nodes = [].slice.call(document.querySelectorAll('.adeptus-stat-value[data-count-to]'));
        if (!nodes.length) {
            return;
        }

        // Fallback if IntersectionObserver not available
        if (!('IntersectionObserver' in window)) {
            nodes.forEach(function(el) {
                animateCount(el, el.getAttribute('data-count-to'), {
                    duration: 1400,
                    format: numberFormatter
                });
            });
            return;
        }

        var once = new IntersectionObserver(function(entries, obs) {
            entries.forEach(function(e) {
                if (e.isIntersecting) {
                    var el = e.target;
                    animateCount(el, el.getAttribute('data-count-to'), {
                        duration: 1400,
                        format: numberFormatter
                    });
                    obs.unobserve(el);
                }
            });
        }, {threshold: 0.4});

        nodes.forEach(function(el) {
            once.observe(el);
        });
    };

    return {
        /**
         * Initialize the dashboard module.
         */
        init: function() {
            // Start after DOM ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bootCounters);
            } else {
                bootCounters();
            }
        }
    };
});
