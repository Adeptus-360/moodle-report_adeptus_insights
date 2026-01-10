<?php
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
 * API Configuration for Adeptus Insights.
 *
 * Centralized API URL management with hardcoded production URLs.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_adeptus_insights;

defined('MOODLE_INTERNAL') || die();

class api_config {
    /**
     * Get the main Adeptus 360 backend API URL
     *
     * This URL is hardcoded for production use. The backend handles:
     * - Installation registration and verification
     * - License activation and validation
     * - Subscription management
     * - Feature flag management
     * - Usage analytics
     * - Report definitions
     *
     * @return string The base API URL (without trailing slash)
     */
    public static function get_backend_url() {
        global $CFG;

        // Production URL (hardcoded for SaaS deployment)
        $url = 'https://a360backend.stagingwithswift.com/api/v1';

        // Allow override via config.php for development/testing (not exposed in UI)
        // Add this to your config.php: $CFG->adeptus360_api_url_override = 'http://localhost:8000/api/v1';
        if (isset($CFG->adeptus360_api_url_override) && !empty($CFG->adeptus360_api_url_override)) {
            $url = $CFG->adeptus360_api_url_override;
        }

        return rtrim($url, '/');
    }

    /**
     * Get the legacy API URL for backward compatibility
     * Points to the old report/parameter endpoint
     *
     * @deprecated Will be removed once all functionality migrates to get_backend_url()
     * @return string The legacy API URL
     */
    public static function get_legacy_api_url() {
        global $CFG;

        // Legacy backend URL
        $url = 'https://ai-backend.stagingwithswift.com/api';

        // Allow override
        if (isset($CFG->adeptus_legacy_api_url_override)) {
            $url = $CFG->adeptus_legacy_api_url_override;
        }

        return rtrim($url, '/');
    }

    /**
     * Get the AI Assistant backend URL (separate service)
     *
     * This is a separate AI service for natural language report generation.
     * Not part of the main Adeptus 360 backend.
     *
     * @return string The AI backend URL
     */
    public static function get_ai_backend_url() {
        global $CFG;

        // AI backend URL (separate service)
        $url = 'https://swiftlearn.co.uk/opt/adeptus_ai_backend/public';

        // Allow override
        if (isset($CFG->adeptus_ai_backend_url_override)) {
            $url = $CFG->adeptus_ai_backend_url_override;
        }

        return rtrim($url, '/');
    }

    /**
     * Get the Moodle site URL
     *
     * This is the public-facing URL of the Moodle installation.
     * Used for CORS headers, installation registration, and callbacks.
     *
     * @return string The site URL
     */
    public static function get_site_url() {
        global $CFG;

        // Production site URL (hardcoded for SaaS deployment)
        $url = 'https://plugin.stagingwithswift.com';

        // Allow override via config.php
        if (isset($CFG->adeptus360_site_url_override) && !empty($CFG->adeptus360_site_url_override)) {
            $url = $CFG->adeptus360_site_url_override;
        }

        return rtrim($url, '/');
    }

    /**
     * Get the CORS origin header value
     *
     * This is the origin that should be allowed for CORS requests.
     * Defaults to the site URL.
     *
     * @return string The CORS origin
     */
    public static function get_cors_origin() {
        return self::get_site_url();
    }

    /**
     * Get the reports API endpoint URL
     *
     * @return string Full URL to reports endpoint
     */
    public static function get_reports_endpoint() {
        return self::get_backend_url() . '/reports/definitions';
    }

    /**
     * Get the installation API endpoint URL
     *
     * @return string Full URL to installation endpoint
     */
    public static function get_installation_endpoint() {
        return self::get_backend_url() . '/installation';
    }

    /**
     * Get the features API endpoint URL
     *
     * @return string Full URL to features endpoint
     */
    public static function get_features_endpoint() {
        return self::get_backend_url() . '/features';
    }

    /**
     * Get the analytics API endpoint URL
     *
     * @return string Full URL to analytics endpoint
     */
    public static function get_analytics_endpoint() {
        return self::get_backend_url() . '/analytics';
    }

    /**
     * Get the AI login endpoint URL
     *
     * @return string Full URL to AI login endpoint
     */
    public static function get_ai_login_endpoint() {
        return self::get_ai_backend_url() . '/api/auth/login';
    }

    /**
     * Get the AI report generation endpoint URL
     *
     * @return string Full URL to AI report endpoint
     */
    public static function get_ai_report_endpoint() {
        return self::get_ai_backend_url() . '/report-ai';
    }

    /**
     * Check if development mode is enabled
     *
     * @return bool True if in development mode
     */
    public static function is_dev_mode() {
        global $CFG;
        return (defined('ADEPTUS360_DEV_MODE') && ADEPTUS360_DEV_MODE) ||
               ($CFG->debug >= DEBUG_DEVELOPER);
    }
}
