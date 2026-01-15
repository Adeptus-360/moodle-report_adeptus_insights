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
 * Check report creation eligibility AJAX endpoint.
 *
 * This endpoint calls the backend API to check if the user can create a report.
 * The backend is the single source of truth for report limits.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../classes/installation_manager.php');
require_once(__DIR__ . '/../classes/api_config.php');

// Require login and capability
require_login();
$context = context_system::instance();
$PAGE->set_context($context);
require_capability('report/adeptus_insights:view', $context);

// Get parameters
$sesskey = required_param('sesskey', PARAM_ALPHANUM);

// Validate session key
if (!confirm_sesskey($sesskey)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'eligible' => false, 'message' => 'Invalid session key']);
    exit;
}

header('Content-Type: application/json');

try {
    // Get installation manager and API configuration
    $installationmanager = new \report_adeptus_insights\installation_manager();
    $apikey = $installationmanager->get_api_key();
    $backendurl = \report_adeptus_insights\api_config::get_backend_url();

    if (empty($apikey)) {
        throw new Exception('Installation not configured. Please complete plugin setup.');
    }

    // Call backend API to check report eligibility
    // The backend is the ONLY authority for report limits
    $endpoint = rtrim($backendurl, '/') . '/api/v1/report-limits/check';

    $postdata = json_encode(new stdClass()); // Empty object

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-API-Key: ' . $apikey,
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlerror = curl_error($ch);
    curl_close($ch);

    // Handle connection errors - FAIL CLOSED (deny if backend unreachable)
    if ($response === false || !empty($curlerror)) {
        error_log('[Adeptus Insights] Report eligibility check failed - curl error: ' . $curlerror);
        echo json_encode([
            'success' => false,
            'eligible' => false,
            'message' => 'Unable to verify report eligibility. Please try again later.',
            'reason' => 'backend_unreachable',
        ]);
        exit;
    }

    // Handle HTTP errors - FAIL CLOSED
    if ($httpcode !== 200) {
        error_log('[Adeptus Insights] Report eligibility check failed - HTTP ' . $httpcode . ': ' . $response);
        echo json_encode([
            'success' => false,
            'eligible' => false,
            'message' => 'Unable to verify report eligibility. Please try again later.',
            'reason' => 'backend_error',
        ]);
        exit;
    }

    // Parse backend response
    $backenddata = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('[Adeptus Insights] Report eligibility check failed - invalid JSON response');
        echo json_encode([
            'success' => false,
            'eligible' => false,
            'message' => 'Unable to verify report eligibility. Please try again later.',
            'reason' => 'invalid_response',
        ]);
        exit;
    }

    // Return backend response - handle alternate field names
    $reportsused = $backenddata['reports_used'] ?? $backenddata['used'] ?? 0;
    $reportslimit = $backenddata['reports_limit'] ?? $backenddata['limit'] ?? 0;
    $reportsremaining = $backenddata['reports_remaining'] ?? $backenddata['remaining'] ?? 0;

    echo json_encode([
        'success' => $backenddata['success'] ?? false,
        'eligible' => $backenddata['eligible'] ?? false,
        'message' => $backenddata['message'] ?? 'Unknown status',
        'reason' => $backenddata['reason'] ?? null,
        'reports_used' => $reportsused,
        'reports_limit' => $reportslimit,
        'reports_remaining' => $reportsremaining,
    ]);
} catch (Exception $e) {
    error_log('[Adeptus Insights] Report eligibility check exception: ' . $e->getMessage());
    // FAIL CLOSED - deny on any error
    echo json_encode([
        'success' => false,
        'eligible' => false,
        'message' => 'Unable to verify report eligibility: ' . $e->getMessage(),
        'reason' => 'exception',
    ]);
}

exit;
