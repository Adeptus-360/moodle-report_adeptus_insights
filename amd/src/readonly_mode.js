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
 * Read-only mode functionality for Adeptus Insights plugin.
 *
 * Manages read-only mode activation/deactivation based on authentication
 * status, disabling interactive elements and forms when unauthorized.
 *
 * @module     report_adeptus_insights/readonly_mode
 * @package
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/notification', 'core/str'], function(Notification, Str) {
    'use strict';

    /** @var {Object} strings - Loaded language strings. */
    var strings = {};

    /**
     * Load required language strings.
     * @returns {Promise} Promise that resolves when strings are loaded.
     */
    var loadStrings = function() {
        return Str.get_strings([
            {key: 'js_plugin_readonly_mode', component: 'report_adeptus_insights'}
        ]).then(function(results) {
            strings.readonlyMessage = results[0];
            return strings;
        }).catch(function() {
            // Fallback if string loading fails.
            strings.readonlyMessage = 'Plugin is in read-only mode due to authentication issues';
            return strings;
        });
    };

    /**
     * Read-only mode functionality for Adeptus Insights plugin
     */
    var ReadonlyMode = {

        /**
         * Initialize read-only mode
         */
        init: function() {
            // Load strings first, then set up event listeners.
            loadStrings().then(function() {
                // Listen for read-only mode enable event.
                document.addEventListener('adeptus:enableReadOnly', function() {
                    ReadonlyMode.enable();
                });

                // Listen for read-only mode disable event.
                document.addEventListener('adeptus:disableReadOnly', function() {
                    ReadonlyMode.disable();
                });

                // Check if we should start in read-only mode.
                if (window.adeptusAuthData) {
                    ReadonlyMode.checkInitialState();
                }
            });
        },

        /**
         * Check initial state and enable read-only if needed
         */
        checkInitialState: function() {
            var authData = window.adeptusAuthData;
            var shouldEnableReadOnly = !authData ||
                                     !authData.user_authorized ||
                                     !authData.has_api_key ||
                                     (authData.auth_errors && authData.auth_errors > 0);

            if (shouldEnableReadOnly) {
                this.enable();
            }
        },

        /**
         * Enable read-only mode
         */
        enable: function() {
            // Add read-only class to body
            document.body.classList.add('adeptus-readonly-mode');

            // Disable all interactive elements
            this.disableInteractiveElements();

            // Show read-only notification
            this.showReadOnlyNotification();

            // Disable forms
            this.disableForms();

            // Disable buttons
            this.disableButtons();

            // Trigger custom event
            document.dispatchEvent(new CustomEvent('adeptus:readOnlyEnabled'));
        },

        /**
         * Disable read-only mode
         */
        disable: function() {
            // Remove read-only class from body
            document.body.classList.remove('adeptus-readonly-mode');

            // Re-enable all interactive elements
            this.enableInteractiveElements();

            // Hide read-only notification
            this.hideReadOnlyNotification();

            // Re-enable forms
            this.enableForms();

            // Re-enable buttons
            this.enableButtons();

            // Trigger custom event
            document.dispatchEvent(new CustomEvent('adeptus:readOnlyDisabled'));
        },

        /**
         * Disable interactive elements
         */
        /** @var {Function|null} _readonlySubmitHandler - Stored submit handler for removal. */
        _readonlySubmitHandler: null,

        disableInteractiveElements: function() {
            // Disable inputs, textareas, selects
            document.querySelectorAll('input, textarea, select').forEach(function(el) {
                el.disabled = true;
            });

            // Disable links that are not navigation
            document.querySelectorAll('a:not(.nav-link):not(.breadcrumb-item a)').forEach(function(el) {
                el.classList.add('disabled');
                el.style.pointerEvents = 'none';
            });

            // Disable buttons
            document.querySelectorAll('button:not(.nav-toggle)').forEach(function(el) {
                el.disabled = true;
            });

            // Disable form submissions
            this._readonlySubmitHandler = function(e) {
                e.preventDefault();
                return false;
            };
            var handler = this._readonlySubmitHandler;
            document.querySelectorAll('form').forEach(function(form) {
                form.addEventListener('submit', handler);
            });
        },

        /**
         * Enable interactive elements
         */
        enableInteractiveElements: function() {
            // Re-enable inputs, textareas, selects
            document.querySelectorAll('input, textarea, select').forEach(function(el) {
                el.disabled = false;
            });

            // Re-enable links
            document.querySelectorAll('a.disabled').forEach(function(el) {
                el.classList.remove('disabled');
                el.style.pointerEvents = '';
            });

            // Re-enable buttons
            document.querySelectorAll('button').forEach(function(el) {
                el.disabled = false;
            });

            // Re-enable form submissions
            if (this._readonlySubmitHandler) {
                var handler = this._readonlySubmitHandler;
                document.querySelectorAll('form').forEach(function(form) {
                    form.removeEventListener('submit', handler);
                });
                this._readonlySubmitHandler = null;
            }
        },

        /**
         * Disable forms
         */
        disableForms: function() {
            document.querySelectorAll('form').forEach(function(form) {
                if (!form.dataset.adeptusOriginalAction) {
                    form.dataset.adeptusOriginalAction = form.getAttribute('action') || '';
                    form.setAttribute('action', '#');
                }
            });
        },

        /**
         * Enable forms
         */
        enableForms: function() {
            document.querySelectorAll('form').forEach(function(form) {
                var originalAction = form.dataset.adeptusOriginalAction;
                if (originalAction !== undefined) {
                    form.setAttribute('action', originalAction);
                    delete form.dataset.adeptusOriginalAction;
                }
            });
        },

        /**
         * Disable buttons
         */
        disableButtons: function() {
            document.querySelectorAll('button:not(.nav-toggle)').forEach(function(btn) {
                if (!btn.dataset.adeptusOriginalText) {
                    btn.dataset.adeptusOriginalText = btn.textContent;
                    btn.textContent = 'Read-Only Mode';
                    btn.classList.add('btn-secondary');
                    btn.classList.remove('btn-primary', 'btn-success', 'btn-danger', 'btn-warning', 'btn-info');
                }
            });
        },

        /**
         * Enable buttons
         */
        enableButtons: function() {
            document.querySelectorAll('button').forEach(function(btn) {
                var originalText = btn.dataset.adeptusOriginalText;
                if (originalText) {
                    btn.textContent = originalText;
                    delete btn.dataset.adeptusOriginalText;
                    btn.classList.remove('btn-secondary');
                }
            });
        },

        /**
         * Show read-only notification
         */
        showReadOnlyNotification: function() {
            var message = strings.readonlyMessage || 'Plugin is in read-only mode due to authentication issues';
            if (Notification) {
                this.readOnlyNotification = Notification.addNotification({
                    message: message,
                    type: 'warning',
                    closebutton: false
                });
            } else {
                // Fallback to alert if Notification is not available.
                if (!this.readOnlyAlertShown) {
                    // eslint-disable-next-line no-alert
                    alert(message);
                    this.readOnlyAlertShown = true;
                }
            }
        },

        /**
         * Hide read-only notification
         */
        hideReadOnlyNotification: function() {
            if (this.readOnlyNotification && Notification) {
                Notification.closeNotification(this.readOnlyNotification);
                this.readOnlyNotification = null;
            }
            this.readOnlyAlertShown = false;
        },

        /**
         * Check if read-only mode is active
         */
        isEnabled: function() {
            return document.body.classList.contains('adeptus-readonly-mode');
        }
    };

    return ReadonlyMode;
});
