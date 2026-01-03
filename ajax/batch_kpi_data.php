<?php
// This file is part of Moodle - http://moodle.org/
//
// Batch KPI data endpoint - optimized for loading multiple KPI metrics at once
// Reduces API calls and improves performance for KPI card display

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
$reportids_json = required_param('reportids', PARAM_RAW);
$sesskey = required_param('sesskey', PARAM_ALPHANUM);

// Validate session key
if (!confirm_sesskey($sesskey)) {
    echo json_encode(['success' => false, 'message' => 'Invalid session key']);
    exit;
}

// Parse report IDs
$reportids = json_decode($reportids_json, true);
if (!is_array($reportids) || empty($reportids)) {
    echo json_encode(['success' => false, 'message' => 'Invalid report IDs']);
    exit;
}

// Limit to max 10 reports per batch
$reportids = array_slice($reportids, 0, 10);

$start_time = microtime(true);

try {
    // Get backend API configuration
    $backendEnabled = isset($CFG->adeptus_wizard_enable_backend_api) ? $CFG->adeptus_wizard_enable_backend_api : true;
    $backendApiUrl = \report_adeptus_insights\api_config::get_backend_url();
    $apiTimeout = isset($CFG->adeptus_wizard_api_timeout) ? $CFG->adeptus_wizard_api_timeout : 10;

    if (!$backendEnabled) {
        echo json_encode(['success' => false, 'message' => 'Backend API is disabled']);
        exit;
    }

    // Get API key
    require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');
    $installation_manager = new \report_adeptus_insights\installation_manager();
    $api_key = $installation_manager->get_api_key();

    // SINGLE API call to fetch ALL report definitions (cached for this request)
    static $all_reports_cache = null;

    if ($all_reports_cache === null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $backendApiUrl . '/reports/definitions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $apiTimeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-API-Key: ' . $api_key
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$response || $httpCode !== 200) {
            echo json_encode(['success' => false, 'message' => 'Failed to fetch reports from backend']);
            exit;
        }

        $backendData = json_decode($response, true);
        if (!$backendData || !$backendData['success']) {
            echo json_encode(['success' => false, 'message' => 'Invalid response from backend']);
            exit;
        }

        // Index reports by name for fast lookup
        $all_reports_cache = [];
        foreach ($backendData['data'] as $report) {
            $name = trim($report['name']);
            $all_reports_cache[$name] = $report;
        }
    }

    // Load report validator once
    require_once($CFG->dirroot . '/report/adeptus_insights/classes/report_validator.php');

    // Process each report
    $results = [];

    foreach ($reportids as $reportid) {
        $report_start = microtime(true);
        $reportid = trim($reportid);

        // Find report in cached definitions
        $report = $all_reports_cache[$reportid] ?? null;

        if (!$report) {
            $results[$reportid] = [
                'success' => false,
                'error' => 'Report not found'
            ];
            continue;
        }

        // Quick validation (skip detailed validation for KPI)
        $validation = \report_adeptus_insights\report_validator::validate_report($report);
        if (!$validation['valid']) {
            $results[$reportid] = [
                'success' => false,
                'error' => 'Report incompatible',
                'details' => $validation['reason']
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
            $query_results = $DB->get_records_sql($sql, []);

            // Convert to array
            $results_array = [];
            foreach ($query_results as $row) {
                $results_array[] = (array)$row;
            }

            $report_time = round((microtime(true) - $report_start) * 1000);

            $results[$reportid] = [
                'success' => true,
                'results' => $results_array,
                'count' => count($results_array),
                'time_ms' => $report_time
            ];

        } catch (Exception $e) {
            $results[$reportid] = [
                'success' => false,
                'error' => 'Query error: ' . $e->getMessage()
            ];
        }
    }

    $total_time = round((microtime(true) - $start_time) * 1000);

    echo json_encode([
        'success' => true,
        'reports' => $results,
        'total_time_ms' => $total_time,
        'report_count' => count($reportids)
    ]);

} catch (Exception $e) {
    error_log('Error in batch_kpi_data.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

exit;
