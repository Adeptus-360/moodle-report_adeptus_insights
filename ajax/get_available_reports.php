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
    $backendenabled = isset($CFG->adeptus_wizard_enable_backend_api) ? $CFG->adeptus_wizard_enable_backend_api : true;
    $backendapiurl = \report_adeptus_insights\api_config::get_backend_url();
    $apitimeout = isset($CFG->adeptus_wizard_api_timeout) ? $CFG->adeptus_wizard_api_timeout : 5;

    if (!$backendenabled) {
        echo json_encode(['success' => false, 'message' => 'Backend API is disabled']);
        exit;
    }

    // Get API key for authentication
    require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');
    $installationmanager = new \report_adeptus_insights\installation_manager();
    $apikey = $installationmanager->get_api_key();

    // Fetch all reports from backend
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

    // Filter reports using validator
    $filteredreports = \report_adeptus_insights\report_validator::filter_reports($backenddata['data']);

    // Optionally, remove unavailable reports entirely or mark them
    $showunavailable = optional_param('show_unavailable', false, PARAM_BOOL);

    if (!$showunavailable) {
        // Remove incompatible reports
        $filteredreports = array_filter($filteredreports, function ($report) {
            return $report['is_available'];
        });

        // Re-index array
        $filteredreports = array_values($filteredreports);
    }

    echo json_encode([
        'success' => true,
        'data' => $filteredreports,
        'total' => count($backenddata['data']),
        'available' => count($filteredreports),
        'filtered' => count($backenddata['data']) - count($filteredreports),
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching available reports: ' . $e->getMessage(),
    ]);
}
