<?php
// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify.
// it under the terms of the GNU General Public License as published by.
// the Free Software Foundation, either version 3 of the License, or.
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,.
// but WITHOUT ANY WARRANTY; without even the implied warranty of.
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the.
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License.
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * API Configuration for Adeptus Insights.
 *
 * Centralized API URL management with a single production backend URL.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_adeptus_insights;

defined('MOODLE_INTERNAL') || die();

/**
 * API configuration class for Adeptus Insights.
 *
 * Provides centralized API URL management for backend communications.
 */
class api_config {
    /**
     * Production backend URL.
     * This is the single source of truth for the Adeptus 360 backend API.
     */
    private const BACKEND_URL = 'https: // Backend.adeptus360.com/api/v1';

    /**
     * Get the Adeptus 360 backend API URL.
     *
     * This URL handles all backend functionality:
     * - Installation registration and verification
     * - License activation and validation
     * - Subscription management
     * - Feature flag management
     * - Usage analytics
     * - Report definitions
     * - AI chat and reports
     *
     * @return string The base API URL (without trailing slash)
     */
    public static function get_backend_url() {
        global $CFG;

        // Allow override via config.php for development/testing only.
        // Add to config.php: $CFG->adeptus360_backend_url = 'http://localhost:8000/api/v1'.
        if (!empty($CFG->adeptus360_backend_url)) {
            return rtrim($CFG->adeptus360_backend_url, '/');
        }

        return self::BACKEND_URL;
    }

    /**
     * Get the Moodle site URL.
     *
     * Returns the current Moodle installation's URL.
     * Used for CORS headers, installation registration, and callbacks.
     *
     * @return string The site URL (without trailing slash)
     */
    public static function get_site_url() {
        global $CFG;
        return rtrim($CFG->wwwroot, '/');
    }

    /**
     * Get the reports endpoint URL.
     *
     * @return string The reports definitions endpoint URL.
     */
    public static function get_reports_endpoint() {
        return self::get_backend_url() . '/reports/definitions';
    }

    /**
     * Get the installation endpoint URL.
     *
     * @return string The installation endpoint URL.
     */
    public static function get_installation_endpoint() {
        return self::get_backend_url() . '/installation';
    }

    /**
     * Get the features endpoint URL.
     *
     * @return string The features endpoint URL.
     */
    public static function get_features_endpoint() {
        return self::get_backend_url() . '/features';
    }

    /**
     * Get the chat endpoint URL.
     *
     * @return string The chat endpoint URL.
     */
    public static function get_chat_endpoint() {
        return self::get_backend_url() . '/chat';
    }

    /**
     * Get the subscriptions endpoint URL.
     *
     * @return string The subscriptions endpoint URL.
     */
    public static function get_subscriptions_endpoint() {
        return self::get_backend_url() . '/subscriptions';
    }

    /**
     * Get the branding logo endpoint.
     *
     * @return string The branding logo endpoint URL.
     */
    public static function get_branding_endpoint() {
        return self::get_backend_url() . '/branding/logo';
    }

    /**
     * Get the AI login endpoint URL.
     *
     * Used for authenticating with the AI service.
     *
     * @return string The AI login endpoint URL.
     */
    public static function get_ai_login_endpoint() {
        return self::get_backend_url() . '/auth/login';
    }

    /**
     * Get the AI report endpoint URL.
     *
     * Used for AI-powered report generation.
     *
     * @return string The AI report endpoint URL.
     */
    public static function get_ai_report_endpoint() {
        return self::get_backend_url() . '/ai/report';
    }

    /**
     * Get the CORS origin for API requests.
     *
     * Returns the Moodle site URL for use in CORS headers.
     *
     * @return string The CORS origin URL.
     */
    public static function get_cors_origin() {
        return self::get_site_url();
    }

    /**
     * Get the legacy API URL.
     *
     * Provides backward compatibility for older API calls.
     *
     * @return string The legacy API URL (same as backend URL).
     */
    public static function get_legacy_api_url() {
        return self::get_backend_url();
    }
}
