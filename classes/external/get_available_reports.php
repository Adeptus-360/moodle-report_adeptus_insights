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

global $CFG;
require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_multiple_structure;
use external_value;

/**
 * External API for getting available reports from backend.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_available_reports extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'show_unavailable' => new external_value(
                PARAM_BOOL,
                'Whether to include unavailable/incompatible reports',
                VALUE_DEFAULT,
                false
            ),
        ]);
    }

    /**
     * Get available reports filtered by Moodle version compatibility.
     *
     * @param bool $showunavailable Whether to include unavailable reports.
     * @return array List of available reports with metadata.
     */
    public static function execute($showunavailable = false) {
        global $CFG;

        // Parameter validation.
        $params = self::validate_parameters(self::execute_parameters(), [
            'show_unavailable' => $showunavailable,
        ]);

        // Context validation.
        $context = \context_system::instance();
        self::validate_context($context);

        // Capability check.
        require_capability('report/adeptus_insights:view', $context);

        // Check if backend is enabled.
        $backendenabled = isset($CFG->adeptus_wizard_enable_backend_api) ? $CFG->adeptus_wizard_enable_backend_api : true;
        if (!$backendenabled) {
            return [
                'success' => false,
                'message' => get_string('error_backend_disabled', 'report_adeptus_insights'),
                'data' => [],
                'total' => 0,
                'available' => 0,
                'filtered' => 0,
            ];
        }

        // Get API configuration.
        $backendapiurl = \report_adeptus_insights\api_config::get_backend_url();
        $apitimeout = isset($CFG->adeptus_wizard_api_timeout) ? $CFG->adeptus_wizard_api_timeout : 5;

        // Get API key for authentication.
        $installationmanager = new \report_adeptus_insights\installation_manager();
        $apikey = $installationmanager->get_api_key();

        // Fetch all reports from backend.
        $curl = new \curl();
        $curl->setHeader('Content-Type: application/json');
        $curl->setHeader('Accept: application/json');
        $curl->setHeader('X-API-Key: ' . $apikey);
        $options = [
            'CURLOPT_TIMEOUT' => $apitimeout,
            'CURLOPT_SSL_VERIFYPEER' => true,
        ];

        $response = $curl->get($backendapiurl . '/reports/definitions', [], $options);
        $info = $curl->get_info();
        $httpcode = $info['http_code'] ?? 0;

        if (!$response || $httpcode !== 200) {
            return [
                'success' => false,
                'message' => get_string('error_fetch_reports_failed', 'report_adeptus_insights'),
                'data' => [],
                'total' => 0,
                'available' => 0,
                'filtered' => 0,
            ];
        }

        $backenddata = json_decode($response, true);
        if (!$backenddata || !$backenddata['success']) {
            return [
                'success' => false,
                'message' => get_string('error_invalid_backend_response', 'report_adeptus_insights'),
                'data' => [],
                'total' => 0,
                'available' => 0,
                'filtered' => 0,
            ];
        }

        // Filter reports using validator.
        $filteredreports = \report_adeptus_insights\report_validator::filter_reports($backenddata['data']);
        $totalcount = count($backenddata['data']);

        // Optionally remove unavailable reports.
        if (!$params['show_unavailable']) {
            $filteredreports = array_filter($filteredreports, function ($report) {
                return $report['is_available'];
            });
            $filteredreports = array_values($filteredreports);
        }

        // Format reports for external return structure.
        $formattedreports = [];
        foreach ($filteredreports as $report) {
            $formattedreports[] = [
                'name' => $report['name'] ?? '',
                'category' => $report['category'] ?? '',
                'description' => $report['description'] ?? '',
                'charttype' => $report['charttype'] ?? '',
                'is_available' => $report['is_available'] ?? false,
                'compatibility_message' => $report['compatibility_message'] ?? '',
            ];
        }

        return [
            'success' => true,
            'message' => '',
            'data' => $formattedreports,
            'total' => $totalcount,
            'available' => count($formattedreports),
            'filtered' => $totalcount - count($formattedreports),
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the request was successful'),
            'message' => new external_value(PARAM_TEXT, 'Error message if not successful', VALUE_OPTIONAL),
            'data' => new external_multiple_structure(
                new external_single_structure([
                    'name' => new external_value(PARAM_TEXT, 'Report name/identifier'),
                    'category' => new external_value(PARAM_TEXT, 'Report category'),
                    'description' => new external_value(PARAM_RAW, 'Report description'),
                    'charttype' => new external_value(PARAM_ALPHANUMEXT, 'Default chart type'),
                    'is_available' => new external_value(PARAM_BOOL, 'Whether report is available on this Moodle'),
                    'compatibility_message' => new external_value(PARAM_TEXT, 'Compatibility status message'),
                ]),
                'List of available reports'
            ),
            'total' => new external_value(PARAM_INT, 'Total number of reports from backend'),
            'available' => new external_value(PARAM_INT, 'Number of available reports'),
            'filtered' => new external_value(PARAM_INT, 'Number of filtered/incompatible reports'),
        ]);
    }
}
