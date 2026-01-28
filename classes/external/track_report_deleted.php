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
use external_value;
use context_system;

/**
 * External service to track report deletion.
 *
 * This endpoint calls the backend API to track when a report is deleted.
 * The backend is the single source of truth for report counts.
 * Deleting reports frees up slots within the user's limit.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class track_report_deleted extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'report_name' => new external_value(PARAM_TEXT, 'Name of the report'),
            'is_ai_generated' => new external_value(PARAM_BOOL, 'Whether the report was AI generated', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Track report deletion.
     *
     * @param string $reportname Report name
     * @param bool $isaigenerated Whether AI generated
     * @return array Result
     */
    public static function execute(string $reportname, bool $isaigenerated = false): array {
        global $CFG;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'report_name' => $reportname,
            'is_ai_generated' => $isaigenerated,
        ]);

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

            // Call backend API to track report deletion.
            $endpoint = rtrim($backendurl, '/') . '/report-limits/track-deleted';

            $postdata = json_encode([
                'report_name' => $params['report_name'],
                'is_ai_generated' => $params['is_ai_generated'],
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

            // Handle connection errors - log but don't fail the user experience.
            if ($response === false || !empty($curlerror)) {
                debugging('[Adeptus Insights] Report deletion tracking failed - curl error: ' . $curlerror, DEBUG_DEVELOPER);
                return [
                    'success' => true,
                    'error' => false,
                    'message' => get_string('report_deleted_tracking_pending', 'report_adeptus_insights'),
                    'tracking_error' => true,
                    'reports_used' => 0,
                    'reports_limit' => 0,
                    'reports_remaining' => 0,
                ];
            }

            // Handle HTTP errors - log but don't fail.
            if ($httpcode !== 200) {
                debugging('[Adeptus Insights] Report deletion tracking failed - HTTP ' . $httpcode, DEBUG_DEVELOPER);
                return [
                    'success' => true,
                    'error' => false,
                    'message' => get_string('report_deleted_tracking_pending', 'report_adeptus_insights'),
                    'tracking_error' => true,
                    'reports_used' => 0,
                    'reports_limit' => 0,
                    'reports_remaining' => 0,
                ];
            }

            // Parse backend response.
            $backenddata = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                debugging('[Adeptus Insights] Report deletion tracking failed - invalid JSON', DEBUG_DEVELOPER);
                return [
                    'success' => true,
                    'error' => false,
                    'message' => get_string('report_deleted_tracking_pending', 'report_adeptus_insights'),
                    'tracking_error' => true,
                    'reports_used' => 0,
                    'reports_limit' => 0,
                    'reports_remaining' => 0,
                ];
            }

            // Return backend response.
            $reportsused = $backenddata['reports_used'] ?? $backenddata['used'] ?? 0;
            $reportslimit = $backenddata['reports_limit'] ?? $backenddata['limit'] ?? 0;
            $reportsremaining = $backenddata['reports_remaining'] ?? $backenddata['remaining'] ?? 0;

            return [
                'success' => $backenddata['success'] ?? true,
                'error' => false,
                'message' => $backenddata['message'] ?? get_string('report_deletion_tracked_success', 'report_adeptus_insights'),
                'tracking_error' => false,
                'reports_used' => (int) $reportsused,
                'reports_limit' => (int) $reportslimit,
                'reports_remaining' => (int) $reportsremaining,
            ];
        } catch (\Exception $e) {
            debugging('[Adeptus Insights] Report deletion tracking exception: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return [
                'success' => true,
                'error' => false,
                'message' => get_string('report_deleted_tracking_pending', 'report_adeptus_insights'),
                'tracking_error' => true,
                'reports_used' => 0,
                'reports_limit' => 0,
                'reports_remaining' => 0,
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
            'success' => new external_value(PARAM_BOOL, 'Whether the operation was successful'),
            'error' => new external_value(PARAM_BOOL, 'Whether an error occurred'),
            'message' => new external_value(PARAM_TEXT, 'Status message'),
            'tracking_error' => new external_value(PARAM_BOOL, 'Whether tracking specifically failed'),
            'reports_used' => new external_value(PARAM_INT, 'Number of reports used'),
            'reports_limit' => new external_value(PARAM_INT, 'Report limit'),
            'reports_remaining' => new external_value(PARAM_INT, 'Reports remaining'),
        ]);
    }
}
