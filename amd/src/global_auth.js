// jshint ignore:start
define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {
    'use strict';

    /**
     * Global Authentication for Adeptus Insights plugin
     */
    var GlobalAuth = {
        
        /**
         * Initialize global authentication
         */
        init: function() {
            // Removed debug logs for production
            return new Promise((resolve, reject) => {
                // Check if already authenticated
                if (this.isAuthenticated()) {
                    resolve();
                    return;
                }
                
                // Check authentication status
                this.checkAuthStatus().then(() => {
                    resolve();
                }).catch((error) => {
                    console.error('[GlobalAuth] Authentication failed:', error);
                    reject(error);
                });
            });
        },
        
        /**
         * Check authentication status
         */
        checkAuthStatus: function() {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: M.cfg.wwwroot + '/report/adeptus_insights/ajax/get_auth_status.php',
                    method: 'GET',
                    dataType: 'json',
                    success: (response) => {
                        if (response && response.success) {
                            this.handleAuthSuccess(response.data);
                            resolve();
                        } else {
                            this.handleAuthFailure(response.message || 'Authentication failed');
                            reject(new Error(response.message || 'Authentication failed'));
                        }
                    },
                    error: (xhr, status, error) => {
                        console.error('[GlobalAuth] Error getting auth status:', error);
                        this.handleAuthFailure('Failed to check authentication status');
                        reject(new Error('Failed to check authentication status'));
                    }
                });
            });
        },
        
        /**
         * Handle successful authentication
         */
        handleAuthSuccess: function(authData) {
            // Removed sensitive debug log for production

            // Store auth data globally
            window.adeptusAuthData = authData;
            
            // Hide any auth modals
            this.hideModal();
            
            // Enable interactive features
            $(document).trigger('adeptus:enableInteractiveFeatures');
            
            // Update UI elements
            this.updateUIForAuthenticatedUser();
        },
        
        /**
         * Handle authentication failure
         */
        handleAuthFailure: function(message) {
            // Removed debug log for production

            // Show login modal
            this.showModal();
            
            // Disable interactive features
            $(document).trigger('adeptus:enableReadOnly');
        },
        
        /**
         * Show authentication modal
         */
        showModal: function() {
            // Check if modal already exists
            if ($('#global-auth-modal').length) {
                $('#global-auth-modal').modal('show');
                return;
            }
            
            // Create modal HTML
            var modalHtml = this.createModalHTML();
            $('body').append(modalHtml);
            
            // Show modal
            $('#global-auth-modal').modal('show');
            
            // Initialize modal functionality
            this.initModalFunctionality();
        },
        
        /**
         * Hide authentication modal
         */
        hideModal: function() {
            $('#global-auth-modal').modal('hide');
        },
        
        /**
         * Create modal HTML
         */
        createModalHTML: function() {
            return `
                <div class="modal fade" id="global-auth-modal" tabindex="-1" role="dialog" aria-labelledby="global-auth-modal-label" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="global-auth-modal-label">
                                    <i class="fa fa-lock"></i> Adeptus Insights Authentication
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="auth-form">
                                    <div class="form-group mb-3">
                                        <label for="site-url">Site URL</label>
                                        <input type="text" class="form-control" id="site-url" value="${window.location.origin}" disabled>
                                    </div>
                                    <div class="form-group mb-3">
                                        <label for="admin-email">Admin Email</label>
                                        <input type="email" class="form-control" id="admin-email" value="admin@example.com" disabled>
                                    </div>
                                    <div class="form-group mb-3">
                                        <label for="auth-password">Password</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="auth-password" placeholder="Enter your password">
                                            <button class="btn btn-outline-secondary" type="button" id="toggle-password">
                                                <i class="fa fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" id="auth-submit">
                                    <i class="fa fa-sign-in"></i> Authenticate
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        },
        
        /**
         * Initialize modal functionality
         */
        initModalFunctionality: function() {
            // Handle form submission
            $('#auth-submit').on('click', () => {
                this.handleLogin();
            });
            
            // Handle password toggle
            $('#toggle-password').on('click', () => {
                this.togglePasswordVisibility();
            });
            
            // Handle Enter key in password field
            $('#auth-password').on('keypress', (e) => {
                if (e.which === 13) {
                    this.handleLogin();
                }
            });
            
            // Auto-fill site info
            this.autoFillSiteInfo();
            
            // Include custom CSS
            this.includeCustomCSS();
        },
        
        /**
         * Handle login submission
         */
        handleLogin: function() {
            var password = $('#auth-password').val();
            
            if (!password) {
                Notification.addNotification({
                    message: 'Please enter your password',
                    type: 'error'
                });
                return;
            }
            
            // Disable submit button
            $('#auth-submit').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Authenticating...');
            
            // Attempt authentication
            this.authenticate(password).then(() => {
                this.hideModal();
                Notification.addNotification({
                    message: 'Authentication successful!',
                    type: 'success'
                });
            }).catch((error) => {
                Notification.addNotification({
                    message: error.message || 'Authentication failed',
                    type: 'error'
                });
                
                // Re-enable submit button
                $('#auth-submit').prop('disabled', false).html('<i class="fa fa-sign-in"></i> Authenticate');
            });
        },
        
        /**
         * Authenticate with backend
         */
        authenticate: function(password) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: M.cfg.wwwroot + '/report/adeptus_insights/ajax/authenticate.php',
                    method: 'POST',
                    data: {
                        password: password,
                        sesskey: M.cfg.sesskey
                    },
                    dataType: 'json',
                    success: (response) => {
                        if (response && response.success) {
                            this.handleAuthSuccess(response.data);
                            resolve();
                        } else {
                            reject(new Error(response.message || 'Authentication failed'));
                        }
                    },
                    error: (xhr, status, error) => {
                        reject(new Error('Failed to authenticate'));
                    }
                });
            });
        },
        
        /**
         * Toggle password visibility
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
         * Auto-fill site information
         */
        autoFillSiteInfo: function() {
            // Site URL is already filled from window.location.origin
            // Admin email could be filled from user data if available
            if (typeof M !== 'undefined' && M.user && M.user.email) {
                $('#admin-email').val(M.user.email);
            }
        },
        
        /**
         * Include custom CSS
         */
        includeCustomCSS: function() {
            if (!$('#global-auth-modal-css').length) {
                var css = `
                    <style id="global-auth-modal-css">
                        #global-auth-modal .modal-content {
                            border-radius: 15px;
                            border: none;
                            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                        }
                        #global-auth-modal .modal-header {
                            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                            color: white;
                            border-radius: 15px 15px 0 0;
                        }
                        #global-auth-modal .modal-title i {
                            margin-right: 8px;
                        }
                        #global-auth-modal .form-control:disabled {
                            background-color: #f8f9fa;
                            color: #6c757d;
                        }
                        #global-auth-modal .btn-primary {
                            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                            border: none;
                            border-radius: 25px;
                            padding: 10px 25px;
                        }
                        #global-auth-modal .btn-primary:hover {
                            transform: translateY(-2px);
                            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
                        }
                        #global-auth-modal .input-group-text {
                            background-color: #f8f9fa;
                            border-color: #ced4da;
                        }
                    </style>
                `;
                $('head').append(css);
            }
        },
        
        /**
         * Update UI for authenticated user
         */
        updateUIForAuthenticatedUser: function() {
            // Hide auth-related elements
            $('.auth-required').hide();
            $('.auth-not-required').show();
            
            // Update user info if available
            if (window.adeptusAuthData && window.adeptusAuthData.user_name) {
                $('.user-name').text(window.adeptusAuthData.user_name);
            }
        },
        
        /**
         * Check if user is authenticated
         */
        isAuthenticated: function() {
            return window.adeptusAuthData && 
                   window.adeptusAuthData.user_authorized && 
                   window.adeptusAuthData.has_api_key;
        },
        
        /**
         * Get authentication data
         */
        getAuthData: function() {
            return window.adeptusAuthData || null;
        },
        
        /**
         * Logout user
         */
        logout: function() {
            // Clear auth data
            window.adeptusAuthData = null;
            
            // Show login modal
            this.showModal();
            
            // Disable interactive features
            $(document).trigger('adeptus:enableReadOnly');
            
            // Update UI
            this.updateUIForUnauthenticatedUser();
        },
        
        /**
         * Update UI for unauthenticated user
         */
        updateUIForUnauthenticatedUser: function() {
            // Show auth-related elements
            $('.auth-required').show();
            $('.auth-not-required').hide();
            
            // Clear user info
            $('.user-name').text('Guest');
        }
    };

    return GlobalAuth;
});
