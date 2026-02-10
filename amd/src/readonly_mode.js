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

define(['jquery', 'core/notification', 'core/str'], function($, Notification, Str) {
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
                $(document).on('adeptus:enableReadOnly', function() {
                    ReadonlyMode.enable();
                });

                // Listen for read-only mode disable event.
                $(document).on('adeptus:disableReadOnly', function() {
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
            $('body').addClass('adeptus-readonly-mode');

            // Disable all interactive elements
            this.disableInteractiveElements();

            // Show read-only notification
            this.showReadOnlyNotification();

            // Disable forms
            this.disableForms();

            // Disable buttons
            this.disableButtons();

            // Trigger custom event
            $(document).trigger('adeptus:readOnlyEnabled');
        },

        /**
         * Disable read-only mode
         */
        disable: function() {
            // Remove read-only class from body
            $('body').removeClass('adeptus-readonly-mode');

            // Re-enable all interactive elements
            this.enableInteractiveElements();

            // Hide read-only notification
            this.hideReadOnlyNotification();

            // Re-enable forms
            this.enableForms();

            // Re-enable buttons
            this.enableButtons();

            // Trigger custom event
            $(document).trigger('adeptus:readOnlyDisabled');
        },

        /**
         * Disable interactive elements
         */
        disableInteractiveElements: function() {
            // Disable inputs, textareas, selects
            $('input, textarea, select').prop('disabled', true);

            // Disable links that are not navigation
            $('a:not(.nav-link):not(.breadcrumb-item a)').addClass('disabled').css('pointer-events', 'none');

            // Disable buttons
            $('button:not(.nav-toggle)').prop('disabled', true);

            // Disable form submissions
            $('form').on('submit.adeptus-readonly', function(e) {
                e.preventDefault();
                return false;
            });
        },

        /**
         * Enable interactive elements
         */
        enableInteractiveElements: function() {
            // Re-enable inputs, textareas, selects
            $('input, textarea, select').prop('disabled', false);

            // Re-enable links
            $('a.disabled').removeClass('disabled').css('pointer-events', '');

            // Re-enable buttons
            $('button').prop('disabled', false);

            // Re-enable form submissions
            $('form').off('submit.adeptus-readonly');
        },

        /**
         * Disable forms
         */
        disableForms: function() {
            $('form').each(function() {
                var $form = $(this);
                if (!$form.data('adeptus-original-action')) {
                    $form.data('adeptus-original-action', $form.attr('action'));
                    $form.attr('action', '#');
                }
            });
        },

        /**
         * Enable forms
         */
        enableForms: function() {
            $('form').each(function() {
                var $form = $(this);
                var originalAction = $form.data('adeptus-original-action');
                if (originalAction) {
                    $form.attr('action', originalAction);
                    $form.removeData('adeptus-original-action');
                }
            });
        },

        /**
         * Disable buttons
         */
        disableButtons: function() {
            $('button:not(.nav-toggle)').each(function() {
                var $btn = $(this);
                if (!$btn.data('adeptus-original-text')) {
                    $btn.data('adeptus-original-text', $btn.text());
                    $btn.text('Read-Only Mode');
                    $btn.addClass('btn-secondary').removeClass('btn-primary btn-success btn-danger btn-warning btn-info');
                }
            });
        },

        /**
         * Enable buttons
         */
        enableButtons: function() {
            $('button').each(function() {
                var $btn = $(this);
                var originalText = $btn.data('adeptus-original-text');
                if (originalText) {
                    $btn.text(originalText);
                    $btn.removeData('adeptus-original-text');
                    $btn.removeClass('btn-secondary');
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
            return $('body').hasClass('adeptus-readonly-mode');
        }
    };

    return ReadonlyMode;
});
