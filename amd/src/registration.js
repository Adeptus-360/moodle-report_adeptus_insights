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

define(['jquery', 'core/ajax'], function($, Ajax) {
    'use strict';

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
            this.bindEvents();
            this.showStep('welcome');
        },

        /**
         * Bind event handlers.
         */
        bindEvents: function() {
            var self = this;

            // Next step buttons.
            $(document).on('click', '.btn-next-step', function(e) {
                e.preventDefault();
                var nextStep = $(this).data('step');
                if (self.validateCurrentStep()) {
                    self.showStep(nextStep);
                }
            });

            // Previous step buttons.
            $(document).on('click', '.btn-prev-step, .adeptus-btn-back', function(e) {
                e.preventDefault();
                var prevStep = $(this).data('step');
                if (prevStep) {
                    self.showStep(prevStep);
                } else {
                    // Handle back buttons without data-step.
                    var currentStepId = $(this).closest('.adeptus-wizard-step').attr('id');
                    if (currentStepId === 'step-admin-info') {
                        self.showStep('welcome');
                    } else if (currentStepId === 'step-verification') {
                        self.showStep('admin-info');
                    }
                }
            });

            // Register installation button.
            $(document).on('click', '#register-installation', function(e) {
                e.preventDefault();
                self.submitRegistration();
            });

            // Continue to subscription button.
            $(document).on('click', '#continue-to-subscription', function(e) {
                e.preventDefault();
                window.location.href = M.cfg.wwwroot + '/report/adeptus_insights/subscription.php';
            });

            // Go to dashboard button.
            $(document).on('click', '#go-to-dashboard', function(e) {
                e.preventDefault();
                window.location.href = M.cfg.wwwroot + '/report/adeptus_insights/index.php';
            });

            // Error close button.
            $(document).on('click', '#error-close', function(e) {
                e.preventDefault();
                self.hideError();
            });

            // Form input changes - update summary.
            $(document).on('input', '#admin_name, #admin_email', function() {
                self.updateSummary();
            });
        },

        /**
         * Show a specific step.
         *
         * @param {string} stepName The step to show.
         */
        showStep: function(stepName) {
            // Hide all steps.
            $('.adeptus-wizard-step').removeClass('active');

            // Show the requested step.
            var stepId = 'step-' + stepName;
            $('#' + stepId).addClass('active');

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
                var adminName = $('#admin_name').val().trim();
                var adminEmail = $('#admin_email').val().trim();

                if (!adminName) {
                    this.showFieldError('#admin_name', 'Please enter your name');
                    return false;
                }

                if (!adminEmail) {
                    this.showFieldError('#admin_email', 'Please enter your email address');
                    return false;
                }

                if (!this.isValidEmail(adminEmail)) {
                    this.showFieldError('#admin_email', 'Please enter a valid email address');
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
            var $field = $(selector);
            $field.addClass('is-invalid');

            // Remove existing error message.
            $field.next('.invalid-feedback').remove();

            // Add error message.
            $field.after('<div class="invalid-feedback">' + message + '</div>');

            // Remove error on input.
            $field.one('input', function() {
                $(this).removeClass('is-invalid');
                $(this).next('.invalid-feedback').remove();
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
            var adminName = $('#admin_name').val() || '-';
            var adminEmail = $('#admin_email').val() || '-';

            $('#summary-admin-name').text(adminName);
            $('#summary-admin-email').text(adminEmail);

            // Also update hidden form fields.
            $('#form-admin-name').val(adminName);
            $('#form-admin-email').val(adminEmail);
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
                admin_name: this.formData.admin_name || $('#admin_name').val(),
                admin_email: this.formData.admin_email || $('#admin_email').val()
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
                    $('#success-installation-id').text(response.data.installation_id);
                }
                if (response.data.api_key) {
                    // Show masked API key.
                    var maskedKey = response.data.api_key.substring(0, 8) + '...' +
                        response.data.api_key.substring(response.data.api_key.length - 4);
                    $('#success-api-key').text(maskedKey);
                }
            }

            // Show success step.
            this.showStep('success');
        },

        /**
         * Show loading overlay.
         */
        showLoading: function() {
            $('#loading-overlay').addClass('active');
        },

        /**
         * Hide loading overlay.
         */
        hideLoading: function() {
            $('#loading-overlay').removeClass('active');
        },

        /**
         * Show error modal.
         *
         * @param {string} message The error message.
         */
        showError: function(message) {
            $('#error-message').text(message);
            $('#error-overlay').addClass('active');
        },

        /**
         * Hide error modal.
         */
        hideError: function() {
            $('#error-overlay').removeClass('active');
        }
    };

    return Registration;
});
