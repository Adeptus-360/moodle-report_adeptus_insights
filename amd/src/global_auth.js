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
 * Authentication is handled by Moodle's standard login system.
 * This module provides minimal compatibility stubs.
 *
 * @module     report_adeptus_insights/global_auth
 * @copyright  2026 Adeptus 360 <info@adeptus360.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    'use strict';

    /**
     * Global Authentication stub for Adeptus Insights plugin.
     * Authentication is handled by Moodle - this module provides no-op methods for compatibility.
     */
    var GlobalAuth = {

        /**
         * Initialize - no-op since auth is handled by Moodle.
         *
         * @returns {Promise} Promise that resolves immediately
         */
        init: function() {
            return Promise.resolve();
        },

        /**
         * Check if user is authenticated.
         * Always returns true since Moodle handles authentication.
         *
         * @returns {boolean} True
         */
        isAuthenticated: function() {
            return true;
        },

        /**
         * Get authentication data.
         *
         * @returns {Object} Auth data from window or empty object
         */
        getAuthData: function() {
            return window.adeptusAuthData || {};
        },

        /**
         * Show modal - no-op, authentication is handled by Moodle.
         */
        showModal: function() {
            // No-op - authentication handled by Moodle login.
        },

        /**
         * Hide modal - no-op.
         */
        hideModal: function() {
            // No-op.
        },

        /**
         * Logout - redirect to Moodle logout.
         */
        logout: function() {
            window.location.href = M.cfg.wwwroot + '/login/logout.php?sesskey=' + M.cfg.sesskey;
        }
    };

    return GlobalAuth;
});
