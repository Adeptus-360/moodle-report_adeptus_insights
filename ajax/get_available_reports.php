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
 * Get available reports AJAX endpoint.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../classes/api_config.php');
require_once(__DIR__ . '/../classes/report_validator.php');

// Require login and capability
require_login();
require_capability('report/adeptus_insights:view', context_system::instance());

// Set content type
header('Content-Type: application/json');

try {
    // Fetch all reports from backend API
    $backendEnabled = isset($CFG->adeptus_wizard_enable_backend_api) ? $CFG->adeptus_wizard_enable_backend_api : true;
    $backendApiUrl = \report_adeptus_insights\api_config::get_backend_url();
    $apiTimeout = isset($CFG->adeptus_wizard_api_timeout) ? $CFG->adeptus_wizard_api_timeout : 5;

    if (!$backendEnabled) {
        echo json_encode(['success' => false, 'message' => 'Backend API is disabled']);
        exit;
    }

    // Get API key for authentication
    require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');
    $installation_manager = new \report_adeptus_insights\installation_manager();
    $api_key = $installation_manager->get_api_key();

    // Fetch all reports from backend
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $backendApiUrl . '/reports/definitions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $apiTimeout);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-API-Key: ' . $api_key,
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

    // Filter reports using validator
    $filtered_reports = \report_adeptus_insights\report_validator::filter_reports($backendData['data']);

    // Optionally, remove unavailable reports entirely or mark them
    $show_unavailable = optional_param('show_unavailable', false, PARAM_BOOL);

    if (!$show_unavailable) {
        // Remove incompatible reports
        $filtered_reports = array_filter($filtered_reports, function ($report) {
            return $report['is_available'];
        });

        // Re-index array
        $filtered_reports = array_values($filtered_reports);
    }

    echo json_encode([
        'success' => true,
        'data' => $filtered_reports,
        'total' => count($backendData['data']),
        'available' => count($filtered_reports),
        'filtered' => count($backendData['data']) - count($filtered_reports),
    ]);
} catch (Exception $e) {
    error_log('Error in get_available_reports.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching available reports: ' . $e->getMessage(),
    ]);
}
