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
 * Registration wizard functionality for Adeptus Insights plugin.
 *
 * @module     report_adeptus_insights/registration
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/str'], function(Ajax, Str) {
    'use strict';

    /**
     * Localized strings loaded from Moodle language pack.
     * @type {Object}
     */
    var STRINGS = {};

    /**
     * Registration wizard module.
     */
    var Registration = {
        /**
         * Current step in the wizard.
         * @type {string}
         */
        currentStep: 'welcome',

        /**
         * Form data collected during registration.
         * @type {Object}
         */
        formData: {},

        /**
         * Initialize the registration wizard.
         */
        init: function() {
            var self = this;
            Str.get_strings([
                {key: 'please_enter_name', component: 'report_adeptus_insights'},
                {key: 'please_enter_email', component: 'report_adeptus_insights'},
                {key: 'please_enter_valid_email', component: 'report_adeptus_insights'},
            ]).then(function(results) {
                STRINGS.pleaseEnterName = results[0];
                STRINGS.pleaseEnterEmail = results[1];
                STRINGS.pleaseEnterValidEmail = results[2];
                return true;
            }).catch(function() {
                STRINGS.pleaseEnterName = 'Please enter your name';
                STRINGS.pleaseEnterEmail = 'Please enter your email address';
                STRINGS.pleaseEnterValidEmail = 'Please enter a valid email address';
            });
            self.bindEvents();
            self.showStep('welcome');
        },

        /**
         * Bind event handlers.
         */
        bindEvents: function() {
            var self = this;

            // Next step buttons.
            document.addEventListener('click', function(e) {
                var btn = e.target.closest('.btn-next-step');
                if (btn) {
                    e.preventDefault();
                    var nextStep = btn.dataset.step;
                    if (self.validateCurrentStep()) {
                        self.showStep(nextStep);
                    }
                }
            });

            // Previous step buttons.
            document.addEventListener('click', function(e) {
                var btn = e.target.closest('.btn-prev-step, .adeptus-btn-back');
                if (btn) {
                    e.preventDefault();
                    var prevStep = btn.dataset.step;
                    if (prevStep) {
                        self.showStep(prevStep);
                    } else {
                        var wizardStep = btn.closest('.adeptus-wizard-step');
                        var currentStepId = wizardStep ? wizardStep.id : '';
                        if (currentStepId === 'step-admin-info') {
                            self.showStep('welcome');
                        } else if (currentStepId === 'step-verification') {
                            self.showStep('admin-info');
                        }
                    }
                }
            });

            // Register installation button.
            document.addEventListener('click', function(e) {
                if (e.target.closest('#register-installation')) {
                    e.preventDefault();
                    self.submitRegistration();
                }
            });

            // Continue to subscription button.
            document.addEventListener('click', function(e) {
                if (e.target.closest('#continue-to-subscription')) {
                    e.preventDefault();
                    window.location.href = M.cfg.wwwroot + '/report/adeptus_insights/subscription.php';
                }
            });

            // Go to dashboard button.
            document.addEventListener('click', function(e) {
                if (e.target.closest('#go-to-dashboard')) {
                    e.preventDefault();
                    window.location.href = M.cfg.wwwroot + '/report/adeptus_insights/index.php';
                }
            });

            // Error close button.
            document.addEventListener('click', function(e) {
                if (e.target.closest('#error-close')) {
                    e.preventDefault();
                    self.hideError();
                }
            });

            // Form input changes - update summary.
            document.addEventListener('input', function(e) {
                if (e.target.matches('#admin_name, #admin_email')) {
                    self.updateSummary();
                }
            });
        },

        /**
         * Show a specific step.
         *
         * @param {string} stepName The step to show.
         */
        showStep: function(stepName) {
            // Hide all steps.
            document.querySelectorAll('.adeptus-wizard-step').forEach(function(el) {
                el.classList.remove('active');
            });

            // Show the requested step.
            var stepId = 'step-' + stepName;
            var stepEl = document.getElementById(stepId);
            if (stepEl) {
                stepEl.classList.add('active');
            }

            // Update current step.
            this.currentStep = stepName;

            // Update summary when showing verification step.
            if (stepName === 'verification') {
                this.updateSummary();
            }
        },

        /**
         * Validate the current step before proceeding.
         *
         * @return {boolean} True if validation passes.
         */
        validateCurrentStep: function() {
            if (this.currentStep === 'admin-info') {
                var adminNameEl = document.getElementById('admin_name');
                var adminEmailEl = document.getElementById('admin_email');
                var adminName = adminNameEl ? adminNameEl.value.trim() : '';
                var adminEmail = adminEmailEl ? adminEmailEl.value.trim() : '';

                if (!adminName) {
                    this.showFieldError('#admin_name', STRINGS.pleaseEnterName);
                    return false;
                }

                if (!adminEmail) {
                    this.showFieldError('#admin_email', STRINGS.pleaseEnterEmail);
                    return false;
                }

                if (!this.isValidEmail(adminEmail)) {
                    this.showFieldError('#admin_email', STRINGS.pleaseEnterValidEmail);
                    return false;
                }

                // Store form data.
                this.formData.admin_name = adminName;
                this.formData.admin_email = adminEmail;
            }

            return true;
        },

        /**
         * Show a field error.
         *
         * @param {string} selector The field selector.
         * @param {string} message The error message.
         */
        showFieldError: function(selector, message) {
            var field = document.querySelector(selector);
            if (!field) {
                return;
            }
            field.classList.add('is-invalid');

            // Remove existing error message.
            var existing = field.nextElementSibling;
            if (existing && existing.classList.contains('invalid-feedback')) {
                existing.remove();
            }

            // Add error message.
            var feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            feedback.innerHTML = message;
            field.parentNode.insertBefore(feedback, field.nextSibling);

            // Remove error on input.
            field.addEventListener('input', function handler() {
                field.classList.remove('is-invalid');
                var fb = field.nextElementSibling;
                if (fb && fb.classList.contains('invalid-feedback')) {
                    fb.remove();
                }
                field.removeEventListener('input', handler);
            });
        },

        /**
         * Validate email format.
         *
         * @param {string} email The email to validate.
         * @return {boolean} True if valid.
         */
        isValidEmail: function(email) {
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        },

        /**
         * Update the summary on the verification step.
         */
        updateSummary: function() {
            var adminNameEl = document.getElementById('admin_name');
            var adminEmailEl = document.getElementById('admin_email');
            var adminName = (adminNameEl ? adminNameEl.value : '') || '-';
            var adminEmail = (adminEmailEl ? adminEmailEl.value : '') || '-';

            var summaryName = document.getElementById('summary-admin-name');
            var summaryEmail = document.getElementById('summary-admin-email');
            if (summaryName) { summaryName.textContent = adminName; }
            if (summaryEmail) { summaryEmail.textContent = adminEmail; }

            // Also update hidden form fields.
            var formName = document.getElementById('form-admin-name');
            var formEmail = document.getElementById('form-admin-email');
            if (formName) { formName.value = adminName; }
            if (formEmail) { formEmail.value = adminEmail; }
        },

        /**
         * Submit the registration.
         */
        submitRegistration: function() {
            var self = this;

            // Show loading overlay.
            this.showLoading();

            // Get form data.
            var data = {
                admin_name: this.formData.admin_name || (document.getElementById('admin_name') ? document.getElementById('admin_name').value : ''),
                admin_email: this.formData.admin_email || (document.getElementById('admin_email') ? document.getElementById('admin_email').value : '')
            };

            // Make AJAX call to register.
            Ajax.call([{
                methodname: 'report_adeptus_insights_register_plugin',
                args: data,
                done: function(response) {
                    self.hideLoading();
                    if (response.success) {
                        self.handleRegistrationSuccess(response);
                    } else {
                        self.showError(response.message || 'Registration failed');
                    }
                },
                fail: function(error) {
                    self.hideLoading();
                    self.showError(error.message || 'An error occurred during registration');
                }
            }]);
        },

        /**
         * Handle successful registration.
         *
         * @param {Object} response The registration response.
         */
        handleRegistrationSuccess: function(response) {
            // Update success details.
            if (response.data) {
                if (response.data.installation_id) {
                    var instIdEl = document.getElementById('success-installation-id');
                    if (instIdEl) { instIdEl.textContent = response.data.installation_id; }
                }
                if (response.data.api_key) {
                    // Show masked API key.
                    var maskedKey = response.data.api_key.substring(0, 8) + '...' +
                        response.data.api_key.substring(response.data.api_key.length - 4);
                    var apiKeyEl = document.getElementById('success-api-key');
                    if (apiKeyEl) { apiKeyEl.textContent = maskedKey; }
                }
            }

            // Show success step.
            this.showStep('success');
        },

        /**
         * Show loading overlay.
         */
        showLoading: function() {
            var overlay = document.getElementById('loading-overlay');
            if (overlay) { overlay.classList.add('active'); }
        },

        /**
         * Hide loading overlay.
         */
        hideLoading: function() {
            var overlay = document.getElementById('loading-overlay');
            if (overlay) { overlay.classList.remove('active'); }
        },

        /**
         * Show error modal.
         *
         * @param {string} message The error message.
         */
        showError: function(message) {
            var errMsg = document.getElementById('error-message');
            if (errMsg) { errMsg.textContent = message; }
            var errOverlay = document.getElementById('error-overlay');
            if (errOverlay) { errOverlay.classList.add('active'); }
        },

        /**
         * Hide error modal.
         */
        hideError: function() {
            var errOverlay = document.getElementById('error-overlay');
            if (errOverlay) { errOverlay.classList.remove('active'); }
        }
    };

    return Registration;
});
