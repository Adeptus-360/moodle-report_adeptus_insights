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

namespace report_adeptus_insights\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use context_system;

/**
 * External service for batch KPI data fetching.
 *
 * Optimized for loading multiple KPI metrics at once.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class batch_kpi_data extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'reportids' => new external_value(PARAM_RAW, 'JSON-encoded array of report IDs'),
        ]);
    }

    /**
     * Fetch batch KPI data.
     *
     * @param string $reportids JSON-encoded array of report IDs
     * @return array Result
     */
    public static function execute(string $reportids): array {
        global $CFG, $DB;

        // Validate parameters.
        $params = self::validate_parameters(self::execute_parameters(), [
            'reportids' => $reportids,
        ]);

        // Validate context.
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('report/adeptus_insights:view', $context);

        // Parse report IDs.
        $reportidsarray = json_decode($params['reportids'], true);
        if (!is_array($reportidsarray) || empty($reportidsarray)) {
            return [
                'success' => false,
                'message' => get_string('error_invalid_report_ids', 'report_adeptus_insights'),
                'reports' => '{}',
                'total_time_ms' => 0,
                'report_count' => 0,
            ];
        }

        // Limit to max 10 reports per batch.
        $reportidsarray = array_slice($reportidsarray, 0, 10);

        $starttime = microtime(true);

        try {
            // Get backend API configuration.
            $backendenabled = isset($CFG->adeptus_wizard_enable_backend_api) ? $CFG->adeptus_wizard_enable_backend_api : true;
            $backendapiurl = \report_adeptus_insights\api_config::get_backend_url();
            $apitimeout = isset($CFG->adeptus_wizard_api_timeout) ? $CFG->adeptus_wizard_api_timeout : 10;

            if (!$backendenabled) {
                return [
                    'success' => false,
                    'message' => get_string('error_backend_disabled', 'report_adeptus_insights'),
                    'reports' => '{}',
                    'total_time_ms' => 0,
                    'report_count' => 0,
                ];
            }

            // Get API key.
            $installationmanager = new \report_adeptus_insights\installation_manager();
            $apikey = $installationmanager->get_api_key();

            // Fetch ALL report definitions from backend.
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
                    'reports' => '{}',
                    'total_time_ms' => 0,
                    'report_count' => 0,
                ];
            }

            $backenddata = json_decode($response, true);
            if (!$backenddata || !$backenddata['success']) {
                return [
                    'success' => false,
                    'message' => get_string('error_invalid_backend_response', 'report_adeptus_insights'),
                    'reports' => '{}',
                    'total_time_ms' => 0,
                    'report_count' => 0,
                ];
            }

            // Index reports by name for fast lookup.
            $allreportscache = [];
            foreach ($backenddata['data'] as $report) {
                $name = trim($report['name']);
                $allreportscache[$name] = $report;
            }

            // Process each report.
            $results = [];

            foreach ($reportidsarray as $reportid) {
                $reportstart = microtime(true);
                $reportid = trim($reportid);

                // Find report in cached definitions.
                $report = $allreportscache[$reportid] ?? null;

                if (!$report) {
                    $results[$reportid] = [
                        'success' => false,
                        'error' => get_string('error_report_not_found', 'report_adeptus_insights'),
                    ];
                    continue;
                }

                // Quick validation.
                $validation = \report_adeptus_insights\report_validator::validate_report($report);
                if (!$validation['valid']) {
                    $results[$reportid] = [
                        'success' => false,
                        'error' => get_string('error_report_incompatible', 'report_adeptus_insights'),
                        'details' => $validation['reason'],
                    ];
                    continue;
                }

                // Execute SQL query.
                try {
                    $sql = $report['sqlquery'];

                    // Add safety limit if needed.
                    if (!preg_match('/\bLIMIT\s+(\d+|:\w+|\?)/i', $sql)) {
                        $sql = rtrim(rtrim($sql), ';') . ' LIMIT 10000';
                    }

                    // Execute query with no parameters (KPI cards typically don't need params).
                    $queryresults = $DB->get_records_sql($sql, []);

                    // Convert to array.
                    $resultsarray = [];
                    foreach ($queryresults as $row) {
                        $resultsarray[] = (array)$row;
                    }

                    $reporttime = round((microtime(true) - $reportstart) * 1000);

                    $results[$reportid] = [
                        'success' => true,
                        'results' => $resultsarray,
                        'count' => count($resultsarray),
                        'time_ms' => $reporttime,
                    ];
                } catch (\Exception $e) {
                    $results[$reportid] = [
                        'success' => false,
                        'error' => get_string('error_query', 'report_adeptus_insights', $e->getMessage()),
                    ];
                }
            }

            $totaltime = round((microtime(true) - $starttime) * 1000);

            return [
                'success' => true,
                'message' => '',
                'reports' => json_encode($results),
                'total_time_ms' => (int) $totaltime,
                'report_count' => count($reportidsarray),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => get_string('error_occurred', 'report_adeptus_insights', $e->getMessage()),
                'reports' => '{}',
                'total_time_ms' => 0,
                'report_count' => 0,
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
            'message' => new external_value(PARAM_TEXT, 'Error message if any'),
            'reports' => new external_value(PARAM_RAW, 'JSON-encoded reports results'),
            'total_time_ms' => new external_value(PARAM_INT, 'Total execution time in milliseconds'),
            'report_count' => new external_value(PARAM_INT, 'Number of reports processed'),
        ]);
    }
}
