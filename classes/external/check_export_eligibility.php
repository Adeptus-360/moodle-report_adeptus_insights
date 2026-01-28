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

namespace report_adeptus_insights\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_multiple_structure;
use external_value;
use context_system;

/**
 * External service to check export eligibility.
 *
 * This endpoint calls the backend API to verify export eligibility.
 * The backend is the single source of truth for export limits - no local
 * limit checking is performed to prevent tampering.
 *
 * Security: Fail closed - if backend is unreachable, exports are denied.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check_export_eligibility extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'format' => new external_value(PARAM_ALPHA, 'Export format (pdf, csv, xlsx)'),
        ]);
    }

    /**
     * Check if user is eligible to export in the specified format.
     *
     * @param string $format Export format
     * @return array Eligibility status
     */
    public static function execute(string $format): array {
        global $CFG;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), ['format' => $format]);
        $format = $params['format'];

        // Validate context.
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('report/adeptus_insights:view', $context);

        try {
            // Get installation manager and API configuration.
            $installationmanager = new \report_adeptus_insights\installation_manager();
            $apikey = $installationmanager->get_api_key();
            $backendurl = \report_adeptus_insights\api_config::get_backend_url();

            if (empty($apikey)) {
                throw new \Exception(get_string('error_installation_not_configured', 'report_adeptus_insights'));
            }

            // Call backend API to check export eligibility.
            // The backend is the ONLY authority for export limits.
            $endpoint = rtrim($backendurl, '/') . '/exports/check-eligibility';

            $postdata = json_encode([
                'format' => $format,
            ]);

            $curl = new \curl();
            $curl->setHeader('Content-Type: application/json');
            $curl->setHeader('Accept: application/json');
            $curl->setHeader('X-API-Key: ' . $apikey);
            $options = [
                'CURLOPT_TIMEOUT' => 15,
                'CURLOPT_CONNECTTIMEOUT' => 10,
                'CURLOPT_SSL_VERIFYPEER' => true,
            ];
            $response = $curl->post($endpoint, $postdata, $options);
            $info = $curl->get_info();
            $httpcode = $info['http_code'] ?? 0;
            $curlerror = $curl->get_errno() ? $curl->error : '';

            // Handle connection/timeout errors - FAIL CLOSED.
            if ($response === false || !empty($curlerror)) {
                debugging('[Adeptus Insights] Export eligibility check failed - curl error: ' . $curlerror, DEBUG_DEVELOPER);
                throw new \Exception(get_string('error_verify_eligibility', 'report_adeptus_insights'));
            }

            // Handle HTTP errors - FAIL CLOSED.
            if ($httpcode !== 200) {
                debugging('[Adeptus Insights] Export eligibility check failed - HTTP ' . $httpcode . ': ' . $response, DEBUG_DEVELOPER);

                if ($httpcode === 401) {
                    throw new \Exception(get_string('error_auth_failed', 'report_adeptus_insights'));
                } else if ($httpcode === 403) {
                    throw new \Exception(get_string('error_access_denied', 'report_adeptus_insights'));
                } else if ($httpcode === 404) {
                    throw new \Exception(get_string('error_service_unavailable', 'report_adeptus_insights'));
                } else if ($httpcode >= 500) {
                    throw new \Exception(get_string('error_server', 'report_adeptus_insights'));
                } else {
                    throw new \Exception(get_string('error_verify_eligibility', 'report_adeptus_insights'));
                }
            }

            // Parse backend response.
            $backenddata = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                debugging('[Adeptus Insights] Export eligibility check failed - invalid JSON response', DEBUG_DEVELOPER);
                throw new \Exception(get_string('error_invalid_server_response', 'report_adeptus_insights'));
            }

            if (!isset($backenddata['success'])) {
                debugging('[Adeptus Insights] Export eligibility check failed - missing success field', DEBUG_DEVELOPER);
                throw new \Exception(get_string('error_invalid_server_response', 'report_adeptus_insights'));
            }

            // Return backend response - the backend is authoritative.
            return [
                'success' => $backenddata['success'],
                'error' => false,
                'eligible' => $backenddata['eligible'] ?? false,
                'message' => $backenddata['message'] ?? get_string('unknown_status', 'report_adeptus_insights'),
                'reason' => $backenddata['reason'] ?? '',
                'exports_used' => (int) ($backenddata['exports_used'] ?? 0),
                'exports_limit' => (int) ($backenddata['exports_limit'] ?? 0),
                'exports_remaining' => (int) ($backenddata['exports_remaining'] ?? 0),
                'allowed_formats' => $backenddata['allowed_formats'] ?? [],
            ];
        } catch (\Exception $e) {
            // FAIL CLOSED - deny export if we cannot verify eligibility with backend.
            return [
                'success' => false,
                'error' => true,
                'eligible' => false,
                'message' => $e->getMessage(),
                'reason' => 'exception',
                'exports_used' => 0,
                'exports_limit' => 0,
                'exports_remaining' => 0,
                'allowed_formats' => [],
            ];
        }
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the request was successful'),
            'error' => new external_value(PARAM_BOOL, 'Whether an error occurred'),
            'eligible' => new external_value(PARAM_BOOL, 'Whether user is eligible to export'),
            'message' => new external_value(PARAM_TEXT, 'Status message'),
            'reason' => new external_value(PARAM_TEXT, 'Reason for ineligibility if applicable'),
            'exports_used' => new external_value(PARAM_INT, 'Number of exports used'),
            'exports_limit' => new external_value(PARAM_INT, 'Export limit'),
            'exports_remaining' => new external_value(PARAM_INT, 'Exports remaining'),
            'allowed_formats' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'Allowed format'),
                'List of allowed export formats'
            ),
        ]);
    }
}
