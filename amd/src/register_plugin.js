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
 * Plugin Registration JavaScript Module.
 *
 * Handles form validation and submission for the plugin registration wizard.
 *
 * @module     report_adeptus_insights/register_plugin
 * @package
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/str'], function(Str) {
    'use strict';

    /**
     * Localized strings loaded via core/str API.
     * @type {Object}
     */
    var STRINGS = {};

    /**
     * Required field configuration.
     * @type {Array}
     */
    var requiredFields = [];

    /**
     * Load all required localized strings.
     * @returns {Promise} Promise resolved when strings are loaded
     */
    var loadStrings = function() {
        return Str.get_strings([
            {key: 'site_name_label', component: 'report_adeptus_insights'},
            {key: 'site_url_label', component: 'report_adeptus_insights'},
            {key: 'administrator_name', component: 'report_adeptus_insights'},
            {key: 'administrator_email', component: 'report_adeptus_insights'},
            {key: 'moodle_version', component: 'report_adeptus_insights'},
            {key: 'php_version', component: 'report_adeptus_insights'},
            {key: 'plugin_version', component: 'report_adeptus_insights'},
            {key: 'missing_fields_message', component: 'report_adeptus_insights'},
            {key: 'registration_disabled', component: 'report_adeptus_insights'},
            {key: 'register_plugin', component: 'report_adeptus_insights'},
            {key: 'registering', component: 'report_adeptus_insights'}
        ]).then(function(results) {
            STRINGS = {
                siteName: results[0],
                siteUrl: results[1],
                administratorName: results[2],
                administratorEmail: results[3],
                moodleVersion: results[4],
                phpVersion: results[5],
                pluginVersion: results[6],
                missingFieldsMessage: results[7],
                registrationDisabled: results[8],
                registerPlugin: results[9],
                registering: results[10]
            };
            return STRINGS;
        }).catch(function() {
            // Fallback strings if loading fails
            STRINGS = {
                siteName: 'Site Name',
                siteUrl: 'Site URL',
                administratorName: 'Administrator Name',
                administratorEmail: 'Administrator Email',
                moodleVersion: 'Moodle Version',
                phpVersion: 'PHP Version',
                pluginVersion: 'Plugin Version',
                missingFieldsMessage: 'The following required fields are missing: {fields}',
                registrationDisabled: 'Registration Disabled',
                registerPlugin: 'Register Plugin',
                registering: 'Registering...'
            };
            return STRINGS;
        });
    };

    /**
     * Validate required fields.
     * @returns {boolean} True if all fields are valid
     */
    var validateFields = function() {
        var missingFields = [];
        var submitBtn = document.getElementById('register-btn');
        var validationError = document.getElementById('validation-error');
        var validationMessage = document.getElementById('validation-message');

        requiredFields.forEach(function(field) {
            var value = field.value ? field.value.trim() : '';
            if (!value || value === '' || value === 'undefined' || value === 'null') {
                missingFields.push(field.name);
            }
        });

        if (missingFields.length > 0) {
            // Show validation error
            if (validationError) {
                validationError.style.display = 'block';
            }
            var message = STRINGS.missingFieldsMessage.replace('{fields}',
                '<strong>' + missingFields.join(', ') + '</strong>');
            if (validationMessage) {
                validationMessage.innerHTML = message;
            }

            // Disable submit button
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.style.opacity = '0.5';
                submitBtn.style.cursor = 'not-allowed';
                submitBtn.innerHTML = '<i class="fa fa-ban"></i> ' + STRINGS.registrationDisabled;
            }

            return false;
        } else {
            // Hide validation error
            if (validationError) {
                validationError.style.display = 'none';
            }

            // Enable submit button
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
                submitBtn.style.cursor = 'pointer';
                submitBtn.innerHTML = '<i class="fa fa-check"></i> ' + STRINGS.registerPlugin;
            }

            return true;
        }
    };

    /**
     * Initialize event handlers.
     */
    var initEventHandlers = function() {
        var form = document.getElementById('registration-form');
        var submitBtn = document.getElementById('register-btn');
        var loadingOverlay = document.getElementById('loading-overlay');

        if (!form) {
            return;
        }

        form.addEventListener('submit', function(e) {
            // Validate before submitting
            if (!validateFields()) {
                e.preventDefault();
                return false;
            }

            // Show loading overlay
            if (loadingOverlay) {
                loadingOverlay.style.display = 'block';
            }
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> ' + STRINGS.registering;
            }

            // Form will submit normally
            return true;
        });
    };

    return {
        /**
         * Initialize the registration module.
         * @param {Object} config - Configuration object from PHP
         * @param {Object} config.fieldValues - Values for required fields
         */
        init: function(config) {
            var fieldValues = config.fieldValues || {};

            loadStrings().then(function() {
                // Build required fields array with current values
                requiredFields = [
                    {id: 'site_name', name: STRINGS.siteName, value: fieldValues.siteName || ''},
                    {id: 'site_url', name: STRINGS.siteUrl, value: fieldValues.siteUrl || ''},
                    {id: 'admin_name', name: STRINGS.administratorName, value: fieldValues.adminName || ''},
                    {id: 'admin_email', name: STRINGS.administratorEmail, value: fieldValues.adminEmail || ''},
                    {id: 'moodle_version', name: STRINGS.moodleVersion, value: fieldValues.moodleVersion || ''},
                    {id: 'php_version', name: STRINGS.phpVersion, value: fieldValues.phpVersion || ''},
                    {id: 'plugin_version', name: STRINGS.pluginVersion, value: fieldValues.pluginVersion || ''}
                ];

                // Run initial validation
                validateFields();

                // Set up event handlers
                initEventHandlers();
            });
        }
    };
});
