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
 * Authentication utilities for Adeptus Insights plugin.
 *
 * Provides helper functions for managing authentication state, checking
 * authorization status, and handling read-only mode based on auth status.
 *
 * @module     report_adeptus_insights/auth_utils
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {
    'use strict';

    /**
     * Authentication utilities for Adeptus Insights plugin
     */
    var AuthUtils = {

        /**
         * Initialize authentication from Moodle data.
         *
         * @param {Object} authData - Authentication data from Moodle
         */
        initializeFromMoodle: function(authData) {
            if (!authData) {
                return;
            }

            // Transform subscription data to ensure consistent field names.
            if (authData.subscription) {
                // Map ai_credits fields to total_credits fields for display compatibility.
                /* eslint-disable camelcase */
                if (authData.subscription.ai_credits_used_this_month !== undefined) {
                    authData.subscription.total_credits_used_this_month = authData.subscription.ai_credits_used_this_month;
                }
                if (authData.subscription.plan_ai_credits_limit !== undefined) {
                    authData.subscription.plan_total_credits_limit = authData.subscription.plan_ai_credits_limit;
                }

                // Also map from plan object if available.
                if (authData.plan && authData.plan.ai_credits) {
                    authData.subscription.plan_total_credits_limit = authData.plan.ai_credits;
                }
                /* eslint-enable camelcase */
            }

            // Store auth data globally.
            window.adeptusAuthData = authData;

            // Initialize read-only mode if needed.
            this.checkReadOnlyMode(authData);

            // Initialize other auth-dependent features.
            this.initializeAuthFeatures(authData);
        },

        /**
         * Check if read-only mode should be enabled.
         *
         * @param {Object} authData - Authentication data to check
         */
        checkReadOnlyMode: function(authData) {
            var shouldEnableReadOnly = !authData ||
                                     !authData.user_authorized ||
                                     !authData.has_api_key ||
                                     (authData.auth_errors && authData.auth_errors > 0);

            if (shouldEnableReadOnly) {
                // Trigger read-only mode initialization.
                $(document).trigger('adeptus:enableReadOnly');
            }
        },

        /**
         * Initialize authentication-dependent features.
         *
         * @param {Object} authData - Authentication data
         */
        initializeAuthFeatures: function(authData) {
            if (authData && authData.user_authorized && authData.has_api_key) {
                // Enable interactive features.
                $(document).trigger('adeptus:enableInteractiveFeatures');

                // Initialize AI Assistant if available.
                if (typeof window.AdeptusAI !== 'undefined') {
                    window.AdeptusAI.init();
                }
            }
        },

        /**
         * Check if user is authenticated.
         *
         * @returns {boolean} True if user is authenticated
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
         * Get authentication status (alias for getAuthData for compatibility).
         *
         * @returns {Object|null} Authentication data or null
         */
        getAuthStatus: function() {
            return window.adeptusAuthData || null;
        },

        /**
         * Get authentication headers for API requests.
         *
         * @returns {Object} Headers object with authorization
         */
        getAuthHeaders: function() {
            var authData = this.getAuthData();
            if (authData && authData.api_key) {
                return {
                    'Authorization': 'Bearer ' + authData.api_key,
                    'X-API-Key': authData.api_key
                };
            }
            return {};
        },

        /**
         * Set/update authentication status with field name mapping.
         *
         * @param {Object} authData - Authentication data to set
         */
        setAuthStatus: function(authData) {
            if (!authData) {
                return;
            }

            // Transform subscription data to ensure consistent field names.
            if (authData.subscription) {
                // Map ai_credits fields to total_credits fields for display compatibility.
                /* eslint-disable camelcase */
                if (authData.subscription.ai_credits_used_this_month !== undefined) {
                    authData.subscription.total_credits_used_this_month = authData.subscription.ai_credits_used_this_month;
                }
                if (authData.subscription.plan_ai_credits_limit !== undefined) {
                    authData.subscription.plan_total_credits_limit = authData.subscription.plan_ai_credits_limit;
                }

                // Also map from plan object if available.
                if (authData.plan && authData.plan.ai_credits) {
                    authData.subscription.plan_total_credits_limit = authData.plan.ai_credits;
                }
                /* eslint-enable camelcase */
            }

            window.adeptusAuthData = authData;
        },

        /**
         * Clear authentication data.
         */
        clearAuthData: function() {
            window.adeptusAuthData = null;
        },

        /**
         * Show authentication error.
         *
         * @param {string} message - Error message to display
         */
        showAuthError: function(message) {
            var errorMsg = message || 'Authentication required';
            if (Notification) {
                Notification.addNotification({
                    message: errorMsg,
                    type: 'error'
                });
            } else {
                // Fallback for when Notification is not available.
                Notification.alert('Authentication Error', errorMsg);
            }
        },

        /**
         * Refresh authentication status.
         * Note: Auth data is now provided directly from PHP, so this function is no longer needed.
         * Keeping for backward compatibility but making it a no-op.
         */
        refreshAuthStatus: function() {
            // Auth data is provided directly from PHP via initializeFromMoodle.
            // No need to make additional AJAX calls.
        }
    };

    return AuthUtils;
});
