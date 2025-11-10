// jshint ignore:start
define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {
    'use strict';

    /**
     * Authentication utilities for Adeptus Insights plugin
     */
    var AuthUtils = {
        
        /**
         * Initialize authentication from Moodle data
         */
        initializeFromMoodle: function(authData) {
            console.log('[Auth Utils] Initializing with data:', authData);
            
            if (!authData) {
                console.warn('[Auth Utils] No moodle auth data provided');
                return;
            }
            
            // Transform subscription data to ensure consistent field names
            if (authData.subscription) {
                // Map ai_credits fields to total_credits fields for display compatibility
                if (authData.subscription.ai_credits_used_this_month !== undefined) {
                    authData.subscription.total_credits_used_this_month = authData.subscription.ai_credits_used_this_month;
                }
                if (authData.subscription.plan_ai_credits_limit !== undefined) {
                    authData.subscription.plan_total_credits_limit = authData.subscription.plan_ai_credits_limit;
                }
                
                // Also map from plan object if available
                if (authData.plan && authData.plan.ai_credits) {
                    authData.subscription.plan_total_credits_limit = authData.plan.ai_credits;
                }
            }
            
            // Store auth data globally
            window.adeptusAuthData = authData;
            
            // Initialize read-only mode if needed
            this.checkReadOnlyMode(authData);
            
            // Initialize other auth-dependent features
            this.initializeAuthFeatures(authData);
        },
        
        /**
         * Check if read-only mode should be enabled
         */
        checkReadOnlyMode: function(authData) {
            console.log('[Auth Utils] Checking read-only mode with auth data:', authData);
            
            var shouldEnableReadOnly = !authData || 
                                     !authData.user_authorized || 
                                     !authData.has_api_key || 
                                     (authData.auth_errors && authData.auth_errors > 0);
            
            console.log('[Auth Utils] Read-only mode should be enabled:', shouldEnableReadOnly, 
                       '| authData:', !!authData, 
                       '| user_authorized:', authData?.user_authorized, 
                       '| has_api_key:', authData?.has_api_key, 
                       '| auth_errors:', authData?.auth_errors);
            
            if (shouldEnableReadOnly) {
                // Trigger read-only mode initialization
                $(document).trigger('adeptus:enableReadOnly');
            }
        },
        
        /**
         * Initialize authentication-dependent features
         */
        initializeAuthFeatures: function(authData) {
            if (authData && authData.user_authorized && authData.has_api_key) {
                // Enable interactive features
                $(document).trigger('adeptus:enableInteractiveFeatures');
                
                // Initialize AI Assistant if available
                if (typeof window.AdeptusAI !== 'undefined') {
                    window.AdeptusAI.init();
                }
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
         * Get authentication status (alias for getAuthData for compatibility)
         */
        getAuthStatus: function() {
            return window.adeptusAuthData || null;
        },

        /**
         * Get authentication headers for API requests
         */
        getAuthHeaders: function() {
            const authData = this.getAuthData();
            if (authData && authData.api_key) {
                return {
                    'Authorization': 'Bearer ' + authData.api_key,
                    'X-API-Key': authData.api_key
                };
            }
            return {};
        },

        /**
         * Set/update authentication status with field name mapping
         */
        setAuthStatus: function(authData) {
            if (!authData) {
                console.warn('[Auth Utils] Attempting to set null auth data');
                return;
            }
            
            // Transform subscription data to ensure consistent field names
            if (authData.subscription) {
                // Map ai_credits fields to total_credits fields for display compatibility
                if (authData.subscription.ai_credits_used_this_month !== undefined) {
                    authData.subscription.total_credits_used_this_month = authData.subscription.ai_credits_used_this_month;
                }
                if (authData.subscription.plan_ai_credits_limit !== undefined) {
                    authData.subscription.plan_total_credits_limit = authData.subscription.plan_ai_credits_limit;
                }
                
                // Also map from plan object if available
                if (authData.plan && authData.plan.ai_credits) {
                    authData.subscription.plan_total_credits_limit = authData.plan.ai_credits;
                }
            }
            
            window.adeptusAuthData = authData;
            console.log('[Auth Utils] Authentication status updated with mapped fields');
        },

        /**
         * Clear authentication data
         */
        clearAuthData: function() {
            window.adeptusAuthData = null;
            console.log('[Auth Utils] Authentication data cleared');
        },
        
        /**
         * Show authentication error
         */
        showAuthError: function(message) {
            if (Notification) {
                Notification.addNotification({
                    message: message || 'Authentication required',
                    type: 'error'
                });
            } else {
                alert(message || 'Authentication required');
            }
        },
        
        /**
         * Refresh authentication status
         * Note: Auth data is now provided directly from PHP, so this function is no longer needed
         * Keeping for backward compatibility but making it a no-op
         */
        refreshAuthStatus: function() {
            console.log('[Auth Utils] refreshAuthStatus called - auth data is now provided directly from PHP');
            // Auth data is provided directly from PHP via initializeFromMoodle
            // No need to make additional AJAX calls
        }
    };

    return AuthUtils;
});
