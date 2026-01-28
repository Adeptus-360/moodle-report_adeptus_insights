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
 * External service to get reports from backend API.
 *
 * Fetches reports from the backend API, filters by Moodle version compatibility,
 * and organizes them by category.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_reports_from_backend extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Calculate the priority of a report based on keywords.
     *
     * @param array $report The report data.
     * @param array $prioritykeywords Priority keyword configuration.
     * @return int Priority value (1=high, 2=medium, 3=low).
     */
    private static function calculate_report_priority(array $report, array $prioritykeywords): int {
        $text = strtolower($report['name'] . ' ' . ($report['description'] ?? ''));

        foreach ($prioritykeywords['high'] as $keyword) {
            if (strpos($text, $keyword) !== false) {
                return 1; // High priority.
            }
        }

        foreach ($prioritykeywords['medium'] as $keyword) {
            if (strpos($text, $keyword) !== false) {
                return 2; // Medium priority.
            }
        }

        foreach ($prioritykeywords['low'] as $keyword) {
            if (strpos($text, $keyword) !== false) {
                return 3; // Low priority.
            }
        }

        return 2; // Default to medium priority.
    }

    /**
     * Get reports from backend API.
     *
     * @return array Result
     */
    public static function execute(): array {
        global $CFG;

        // Validate context.
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('report/adeptus_insights:view', $context);

        try {
            // Get current Moodle version for compatibility filtering.
            $moodleversionstring = '4.2'; // Hardcoded for now.

            // Backend API configuration.
            $backendenabled = isset($CFG->adeptus_wizard_enable_backend_api) ? $CFG->adeptus_wizard_enable_backend_api : true;
            $backendapiurl = \report_adeptus_insights\api_config::get_backend_url();
            $apitimeout = isset($CFG->adeptus_wizard_api_timeout) ? $CFG->adeptus_wizard_api_timeout : 5;
            $debugmode = isset($CFG->adeptus_debug_mode) ? $CFG->adeptus_debug_mode : false;

            if (!$backendenabled) {
                throw new \Exception(get_string('error_backend_disabled', 'report_adeptus_insights'));
            }

            // Get API key for authentication (optional).
            $apikey = '';
            try {
                $installationmanager = new \report_adeptus_insights\installation_manager();
                $apikey = $installationmanager->get_api_key();
            } catch (\Exception $e) {
                debugging('API key retrieval failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }

            // Prepare headers.
            $headers = [
                'Content-Type: application/json',
                'Accept: application/json',
            ];

            if (!empty($apikey)) {
                $headers[] = 'X-API-Key: ' . $apikey;
            }

            // Fetch reports from backend API.
            $curl = new \curl();
            foreach ($headers as $header) {
                $curl->setHeader($header);
            }
            $options = [
                'CURLOPT_TIMEOUT' => $apitimeout,
                'CURLOPT_SSL_VERIFYPEER' => true,
                'CURLOPT_FOLLOWLOCATION' => true,
                'CURLOPT_MAXREDIRS' => 5,
            ];

            $response = $curl->get($backendapiurl . '/reports/definitions', [], $options);
            $info = $curl->get_info();
            $httpcode = $info['http_code'] ?? 0;
            $curlerror = $curl->get_errno() ? $curl->error : '';

            if ($response === false) {
                throw new \Exception(get_string('error_fetch_reports_failed', 'report_adeptus_insights') . ': ' . $curlerror);
            }

            if ($httpcode !== 200) {
                $errordetails = "HTTP $httpcode";
                if (!empty($curlerror)) {
                    $errordetails .= ", cURL Error: $curlerror";
                }
                throw new \Exception(get_string('error_fetch_reports_failed', 'report_adeptus_insights') . ': ' . $errordetails);
            }

            $backenddata = json_decode($response, true);
            if (!$backenddata || !$backenddata['success']) {
                throw new \Exception(get_string('error_invalid_backend_response', 'report_adeptus_insights'));
            }

            $allreports = $backenddata['data'];

            // Filter reports for Moodle version AND table/module compatibility.
            $compatiblereports = [];

            foreach ($allreports as $report) {
                $iscompatible = true;

                // Check minimum version.
                if (!empty($report['min_moodle_version'])) {
                    if (version_compare($moodleversionstring, $report['min_moodle_version'], '<')) {
                        $iscompatible = false;
                    }
                }

                // Check maximum version.
                if (!empty($report['max_moodle_version'])) {
                    if (version_compare($moodleversionstring, $report['max_moodle_version'], '>')) {
                        $iscompatible = false;
                    }
                }

                // Check table/module compatibility.
                if ($iscompatible) {
                    $validation = \report_adeptus_insights\report_validator::validate_report($report);
                    if (!$validation['valid']) {
                        $iscompatible = false;
                    }
                }

                if ($iscompatible && $report['isactive']) {
                    $compatiblereports[] = $report;
                }
            }

            // Organize reports by category.
            $categories = [];
            foreach ($compatiblereports as $report) {
                $categoryname = $report['category'];

                if (!isset($categories[$categoryname])) {
                    // Remove "Reports" from category name for display.
                    $displayname = str_replace(' Reports', '', $categoryname);

                    $categories[$categoryname] = [
                        'name' => $displayname,
                        'original_name' => $categoryname,
                        'icon' => 'fa-folder-o',
                        'reports' => [],
                        'report_count' => 0,
                        'free_reports_count' => 0,
                    ];
                }

                // Add report to category.
                $categories[$categoryname]['reports'][] = [
                    'id' => $report['name'],
                    'name' => $report['name'],
                    'description' => $report['description'],
                    'charttype' => $report['charttype'],
                    'sqlquery' => $report['sqlquery'],
                    'parameters' => $report['parameters'],
                    'is_free_tier' => false,
                ];
                $categories[$categoryname]['report_count']++;
            }

            // Apply free tier restrictions.
            $prioritykeywords = [
                'high' => ['overview', 'summary', 'total', 'count', 'basic', 'simple', 'main', 'general', 'all', 'complete'],
                'medium' => ['detailed', 'advanced', 'specific', 'custom', 'filtered', 'selected'],
                'low' => ['export', 'bulk', 'batch', 'comprehensive', 'extensive', 'full', 'complete', 'detailed analysis'],
            ];

            foreach ($categories as $catkey => $category) {
                $totalreports = count($category['reports']);

                // Determine how many reports to allow for free tier.
                if ($totalreports >= 1 && $totalreports <= 4) {
                    $freecount = 1;
                } else if ($totalreports >= 5 && $totalreports <= 10) {
                    $freecount = 2;
                } else {
                    $freecount = 3;
                }

                // Sort reports by priority (1 = highest priority).
                usort($categories[$catkey]['reports'], function ($a, $b) use ($prioritykeywords) {
                    $prioritya = self::calculate_report_priority($a, $prioritykeywords);
                    $priorityb = self::calculate_report_priority($b, $prioritykeywords);
                    return $prioritya <=> $priorityb;
                });

                // Mark free tier reports.
                for ($i = 0; $i < count($categories[$catkey]['reports']); $i++) {
                    $categories[$catkey]['reports'][$i]['is_free_tier'] = ($i < $freecount);
                }

                $categories[$catkey]['free_reports_count'] = $freecount;
            }

            return [
                'success' => true,
                'message' => '',
                'categories' => json_encode(array_values($categories)),
                'total_reports' => count($compatiblereports),
                'moodle_version' => $moodleversionstring,
            ];
        } catch (\Exception $e) {
            // Provide user-friendly error messages.
            $message = $e->getMessage();
            if (strpos($message, 'HTTP 301') !== false || strpos($message, 'HTTP 302') !== false) {
                $message = get_string('auth_required', 'report_adeptus_insights');
            } else if (strpos($message, 'Invalid session key') !== false) {
                $message = get_string('session_expired', 'report_adeptus_insights');
            }

            return [
                'success' => false,
                'message' => $message,
                'categories' => '[]',
                'total_reports' => 0,
                'moodle_version' => '',
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
            'categories' => new external_value(PARAM_RAW, 'JSON-encoded categories with reports'),
            'total_reports' => new external_value(PARAM_INT, 'Total number of compatible reports'),
            'moodle_version' => new external_value(PARAM_TEXT, 'Moodle version string'),
        ]);
    }
}
