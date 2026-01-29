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
 * Global authentication handling for Adeptus Insights plugin.
 *
 * Manages global authentication state, login modal display, authentication
 * verification, and UI updates based on authentication status.
 *
 * @module     report_adeptus_insights/global_auth
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {
    'use strict';

    /**
     * Global Authentication for Adeptus Insights plugin
     */
    var GlobalAuth = {

        /**
         * Initialize global authentication.
         *
         * @returns {Promise} Promise that resolves when auth is complete
         */
        init: function() {
            var self = this;
            return new Promise(function(resolve, reject) {
                // Check if already authenticated.
                if (self.isAuthenticated()) {
                    resolve();
                    return;
                }

                // Check authentication status.
                self.checkAuthStatus().then(function() {
                    resolve();
                    return true;
                }).catch(function(err) {
                    reject(err);
                });
            });
        },

        /**
         * Check authentication status.
         *
         * @returns {Promise} Promise that resolves with auth status
         */
        checkAuthStatus: function() {
            var self = this;
            return new Promise(function(resolve, reject) {
                var promises = Ajax.call([{
                    methodname: 'report_adeptus_insights_get_auth_status',
                    args: {}
                }]);

                promises[0].done(function(result) {
                    var response = result.data ? result.data : result;
                    if (response && response.success) {
                        // Build auth data from response.
                        var authData = {
                            is_authenticated: response.is_authenticated,
                            user_email: response.user_email,
                            token_expires_at: response.token_expires_at
                        };
                        // Parse installation_info if it's a JSON string.
                        if (response.installation_info) {
                            try {
                                authData.installation_info = JSON.parse(response.installation_info);
                            } catch (parseError) {
                                authData.installation_info = {};
                            }
                        }
                        self.handleAuthSuccess(authData);
                        resolve();
                    } else {
                        self.handleAuthFailure(response.message || 'Authentication failed');
                        reject(new Error(response.message || 'Authentication failed'));
                    }
                }).fail(function() {
                    self.handleAuthFailure('Failed to check authentication status');
                    reject(new Error('Failed to check authentication status'));
                });
            });
        },

        /**
         * Handle successful authentication.
         *
         * @param {Object} authData - Authentication data
         */
        handleAuthSuccess: function(authData) {
            // Store auth data globally.
            window.adeptusAuthData = authData;

            // Hide any auth modals.
            this.hideModal();

            // Enable interactive features.
            $(document).trigger('adeptus:enableInteractiveFeatures');

            // Update UI elements.
            this.updateUIForAuthenticatedUser();
        },

        /**
         * Handle authentication failure.
         */
        handleAuthFailure: function() {
            // Show login modal.
            this.showModal();

            // Disable interactive features.
            $(document).trigger('adeptus:enableReadOnly');
        },

        /**
         * Show authentication modal.
         */
        showModal: function() {
            // Check if modal already exists.
            if ($('#global-auth-modal').length) {
                $('#global-auth-modal').modal('show');
                return;
            }

            // Create modal HTML.
            var modalHtml = this.createModalHTML();
            $('body').append(modalHtml);

            // Show modal.
            $('#global-auth-modal').modal('show');

            // Initialize modal functionality.
            this.initModalFunctionality();
        },

        /**
         * Hide authentication modal.
         */
        hideModal: function() {
            var $modal = $('#global-auth-modal');
            // Only try to hide if modal exists and is visible.
            if ($modal.length && $modal.hasClass('show')) {
                $modal.modal('hide');
            }
            // Clean up any orphaned modal backdrops that might block clicks.
            $('.modal-backdrop').remove();
            // Ensure body doesn't have modal-open class stuck.
            $('body').removeClass('modal-open');
        },

        /**
         * Create modal HTML.
         *
         * @returns {string} Modal HTML string
         */
        createModalHTML: function() {
            var siteOrigin = window.location.origin;
            return '<div class="modal fade" id="global-auth-modal" tabindex="-1" role="dialog" ' +
                'aria-labelledby="global-auth-modal-label" aria-hidden="true">' +
                '<div class="modal-dialog modal-dialog-centered" role="document">' +
                '<div class="modal-content">' +
                '<div class="modal-header">' +
                '<h5 class="modal-title" id="global-auth-modal-label">' +
                '<i class="fa fa-lock"></i> Adeptus Insights Authentication' +
                '</h5>' +
                '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
                '</div>' +
                '<div class="modal-body">' +
                '<div class="auth-form">' +
                '<div class="form-group mb-3">' +
                '<label for="site-url">Site URL</label>' +
                '<input type="text" class="form-control" id="site-url" value="' + siteOrigin + '" disabled>' +
                '</div>' +
                '<div class="form-group mb-3">' +
                '<label for="admin-email">Admin Email</label>' +
                '<input type="email" class="form-control" id="admin-email" value="admin@example.com" disabled>' +
                '</div>' +
                '<div class="form-group mb-3">' +
                '<label for="auth-password">Password</label>' +
                '<div class="input-group">' +
                '<input type="password" class="form-control" id="auth-password" placeholder="Enter your password">' +
                '<button class="btn btn-outline-secondary" type="button" id="toggle-password">' +
                '<i class="fa fa-eye"></i>' +
                '</button>' +
                '</div>' +
                '</div>' +
                '</div>' +
                '</div>' +
                '<div class="modal-footer">' +
                '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>' +
                '<button type="button" class="btn btn-primary" id="auth-submit">' +
                '<i class="fa fa-sign-in"></i> Authenticate' +
                '</button>' +
                '</div>' +
                '</div>' +
                '</div>' +
                '</div>';
        },

        /**
         * Initialize modal functionality.
         */
        initModalFunctionality: function() {
            var self = this;
            // Handle form submission.
            $('#auth-submit').on('click', function() {
                self.handleLogin();
            });

            // Handle password toggle.
            $('#toggle-password').on('click', function() {
                self.togglePasswordVisibility();
            });

            // Handle Enter key in password field.
            $('#auth-password').on('keypress', function(e) {
                if (e.which === 13) {
                    self.handleLogin();
                }
            });

            // Auto-fill site info.
            this.autoFillSiteInfo();

            // Include custom CSS.
            this.includeCustomCSS();
        },

        /**
         * Handle login submission.
         */
        handleLogin: function() {
            var self = this;
            var password = $('#auth-password').val();

            if (!password) {
                Notification.addNotification({
                    message: 'Please enter your password',
                    type: 'error'
                });
                return;
            }

            // Disable submit button.
            $('#auth-submit').prop('disabled', true)
                .html('<i class="fa fa-spinner fa-spin"></i> Authenticating...');

            // Attempt authentication.
            this.authenticate(password).then(function() {
                self.hideModal();
                Notification.addNotification({
                    message: 'Authentication successful!',
                    type: 'success'
                });
                return true;
            }).catch(function(error) {
                Notification.addNotification({
                    message: error.message || 'Authentication failed',
                    type: 'error'
                });

                // Re-enable submit button.
                $('#auth-submit').prop('disabled', false)
                    .html('<i class="fa fa-sign-in"></i> Authenticate');
            });
        },

        /**
         * Authenticate with backend.
         *
         * @param {string} password - User password
         * @returns {Promise} Promise that resolves on success
         */
        authenticate: function(password) {
            var self = this;
            return new Promise(function(resolve, reject) {
                $.ajax({
                    url: M.cfg.wwwroot + '/report/adeptus_insights/ajax/authenticate.php',
                    method: 'POST',
                    data: {
                        password: password,
                        sesskey: M.cfg.sesskey
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response && response.success) {
                            self.handleAuthSuccess(response.data);
                            resolve();
                        } else {
                            reject(new Error(response.message || 'Authentication failed'));
                        }
                    },
                    error: function() {
                        reject(new Error('Failed to authenticate'));
                    }
                });
            });
        },

        /**
         * Toggle password visibility.
         */
        togglePasswordVisibility: function() {
            var $passwordField = $('#auth-password');
            var $toggleBtn = $('#toggle-password');
            var $icon = $toggleBtn.find('i');

            if ($passwordField.attr('type') === 'password') {
                $passwordField.attr('type', 'text');
                $icon.removeClass('fa-eye').addClass('fa-eye-slash');
            } else {
                $passwordField.attr('type', 'password');
                $icon.removeClass('fa-eye-slash').addClass('fa-eye');
            }
        },

        /**
         * Auto-fill site information.
         */
        autoFillSiteInfo: function() {
            // Site URL is already filled from window.location.origin.
            // Admin email could be filled from user data if available.
            if (typeof M !== 'undefined' && M.user && M.user.email) {
                $('#admin-email').val(M.user.email);
            }
        },

        /**
         * Include custom CSS.
         */
        includeCustomCSS: function() {
            if (!$('#global-auth-modal-css').length) {
                var css = '<style id="global-auth-modal-css">' +
                    '#global-auth-modal .modal-content {' +
                    'border-radius: 15px;' +
                    'border: none;' +
                    'box-shadow: 0 10px 30px rgba(0,0,0,0.2);' +
                    '}' +
                    '#global-auth-modal .modal-header {' +
                    'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);' +
                    'color: white;' +
                    'border-radius: 15px 15px 0 0;' +
                    '}' +
                    '#global-auth-modal .modal-title i {' +
                    'margin-right: 8px;' +
                    '}' +
                    '#global-auth-modal .form-control:disabled {' +
                    'background-color: #f8f9fa;' +
                    'color: #6c757d;' +
                    '}' +
                    '#global-auth-modal .btn-primary {' +
                    'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);' +
                    'border: none;' +
                    'border-radius: 25px;' +
                    'padding: 10px 25px;' +
                    '}' +
                    '#global-auth-modal .btn-primary:hover {' +
                    'transform: translateY(-2px);' +
                    'box-shadow: 0 5px 15px rgba(0,0,0,0.2);' +
                    '}' +
                    '#global-auth-modal .input-group-text {' +
                    'background-color: #f8f9fa;' +
                    'border-color: #ced4da;' +
                    '}' +
                    '</style>';
                $('head').append(css);
            }
        },

        /**
         * Update UI for authenticated user.
         */
        updateUIForAuthenticatedUser: function() {
            // Hide auth-related elements.
            $('.auth-required').hide();
            $('.auth-not-required').show();

            // Update user info if available.
            if (window.adeptusAuthData && window.adeptusAuthData.user_name) {
                $('.user-name').text(window.adeptusAuthData.user_name);
            }
        },

        /**
         * Check if user is authenticated.
         *
         * @returns {boolean} True if authenticated
         */
        isAuthenticated: function() {
            return window.adeptusAuthData &&
                   window.adeptusAuthData.user_authorized &&
                   window.adeptusAuthData.has_api_key;
        },

        /**
         * Get authentication data.
         *
         * @returns {Object|null} Authentication data or null
         */
        getAuthData: function() {
            return window.adeptusAuthData || null;
        },

        /**
         * Logout user.
         */
        logout: function() {
            // Clear auth data.
            window.adeptusAuthData = null;

            // Show login modal.
            this.showModal();

            // Disable interactive features.
            $(document).trigger('adeptus:enableReadOnly');

            // Update UI.
            this.updateUIForUnauthenticatedUser();
        },

        /**
         * Update UI for unauthenticated user.
         */
        updateUIForUnauthenticatedUser: function() {
            // Show auth-related elements.
            $('.auth-required').show();
            $('.auth-not-required').hide();

            // Clear user info.
            $('.user-name').text('Guest');
        }
    };

    return GlobalAuth;
});
