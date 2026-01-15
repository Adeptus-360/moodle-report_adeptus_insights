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
 * Batch KPI data endpoint for Adeptus Insights.
 *
 * Optimized for loading multiple KPI metrics at once.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
define('READ_ONLY_SESSION', true); // Allow parallel requests

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../classes/api_config.php');

// Require login and capability
require_login();
require_capability('report/adeptus_insights:view', context_system::instance());

// Release session lock early to allow parallel requests
\core\session\manager::write_close();

// Set content type
header('Content-Type: application/json');

// Get parameters
$reportidsjson = required_param('reportids', PARAM_RAW);
$sesskey = required_param('sesskey', PARAM_ALPHANUM);

// Validate session key
if (!confirm_sesskey($sesskey)) {
    echo json_encode(['success' => false, 'message' => 'Invalid session key']);
    exit;
}

// Parse report IDs
$reportids = json_decode($reportidsjson, true);
if (!is_array($reportids) || empty($reportids)) {
    echo json_encode(['success' => false, 'message' => 'Invalid report IDs']);
    exit;
}

// Limit to max 10 reports per batch
$reportids = array_slice($reportids, 0, 10);

$starttime = microtime(true);

try {
    // Get backend API configuration
    $backendenabled = isset($CFG->adeptus_wizard_enable_backend_api) ? $CFG->adeptus_wizard_enable_backend_api : true;
    $backendapiurl = \report_adeptus_insights\api_config::get_backend_url();
    $apitimeout = isset($CFG->adeptus_wizard_api_timeout) ? $CFG->adeptus_wizard_api_timeout : 10;

    if (!$backendenabled) {
        echo json_encode(['success' => false, 'message' => 'Backend API is disabled']);
        exit;
    }

    // Get API key
    require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');
    $installationmanager = new \report_adeptus_insights\installation_manager();
    $apikey = $installationmanager->get_api_key();

    // SINGLE API call to fetch ALL report definitions (cached for this request)
    static $allreportscache = null;

    if ($allreportscache === null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $backendapiurl . '/reports/definitions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $apitimeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-API-Key: ' . $apikey,
        ]);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$response || $httpcode !== 200) {
            echo json_encode(['success' => false, 'message' => 'Failed to fetch reports from backend']);
            exit;
        }

        $backenddata = json_decode($response, true);
        if (!$backenddata || !$backenddata['success']) {
            echo json_encode(['success' => false, 'message' => 'Invalid response from backend']);
            exit;
        }

        // Index reports by name for fast lookup
        $allreportscache = [];
        foreach ($backenddata['data'] as $report) {
            $name = trim($report['name']);
            $allreportscache[$name] = $report;
        }
    }

    // Load report validator once
    require_once($CFG->dirroot . '/report/adeptus_insights/classes/report_validator.php');

    // Process each report
    $results = [];

    foreach ($reportids as $reportid) {
        $reportstart = microtime(true);
        $reportid = trim($reportid);

        // Find report in cached definitions
        $report = $allreportscache[$reportid] ?? null;

        if (!$report) {
            $results[$reportid] = [
                'success' => false,
                'error' => 'Report not found',
            ];
            continue;
        }

        // Quick validation (skip detailed validation for KPI)
        $validation = \report_adeptus_insights\report_validator::validate_report($report);
        if (!$validation['valid']) {
            $results[$reportid] = [
                'success' => false,
                'error' => 'Report incompatible',
                'details' => $validation['reason'],
            ];
            continue;
        }

        // Execute SQL query
        try {
            $sql = $report['sqlquery'];

            // Add safety limit if needed
            if (!preg_match('/\bLIMIT\s+(\d+|:\w+|\?)/i', $sql)) {
                $sql = rtrim(rtrim($sql), ';') . ' LIMIT 10000';
            }

            // For KPI, we just need the count or aggregated value
            // Execute query with no parameters for now (KPI cards typically don't need params)
            $queryresults = $DB->get_records_sql($sql, []);

            // Convert to array
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
        } catch (Exception $e) {
            $results[$reportid] = [
                'success' => false,
                'error' => 'Query error: ' . $e->getMessage(),
            ];
        }
    }

    $totaltime = round((microtime(true) - $starttime) * 1000);

    echo json_encode([
        'success' => true,
        'reports' => $results,
        'total_time_ms' => $totaltime,
        'report_count' => count($reportids),
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
    ]);
}

exit;
