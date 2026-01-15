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
    $installation_manager = new \report_adeptus_insights\installation_manager();
    $api_key = $installation_manager->get_api_key();
    $backend_url = \report_adeptus_insights\api_config::get_backend_url();

    if (empty($api_key)) {
        throw new Exception('Installation not configured. Please complete plugin setup.');
    }

    // Call backend API to check report eligibility
    // The backend is the ONLY authority for report limits
    $endpoint = rtrim($backend_url, '/') . '/api/v1/report-limits/check';

    $post_data = json_encode(new stdClass()); // Empty object

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'X-API-Key: ' . $api_key,
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // Handle connection errors - FAIL CLOSED (deny if backend unreachable)
    if ($response === false || !empty($curl_error)) {
        error_log('[Adeptus Insights] Report eligibility check failed - curl error: ' . $curl_error);
        echo json_encode([
            'success' => false,
            'eligible' => false,
            'message' => 'Unable to verify report eligibility. Please try again later.',
            'reason' => 'backend_unreachable',
        ]);
        exit;
    }

    // Handle HTTP errors - FAIL CLOSED
    if ($http_code !== 200) {
        error_log('[Adeptus Insights] Report eligibility check failed - HTTP ' . $http_code . ': ' . $response);
        echo json_encode([
            'success' => false,
            'eligible' => false,
            'message' => 'Unable to verify report eligibility. Please try again later.',
            'reason' => 'backend_error',
        ]);
        exit;
    }

    // Parse backend response
    $backend_data = json_decode($response, true);

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
    $reports_used = $backend_data['reports_used'] ?? $backend_data['used'] ?? 0;
    $reports_limit = $backend_data['reports_limit'] ?? $backend_data['limit'] ?? 0;
    $reports_remaining = $backend_data['reports_remaining'] ?? $backend_data['remaining'] ?? 0;

    echo json_encode([
        'success' => $backend_data['success'] ?? false,
        'eligible' => $backend_data['eligible'] ?? false,
        'message' => $backend_data['message'] ?? 'Unknown status',
        'reason' => $backend_data['reason'] ?? null,
        'reports_used' => $reports_used,
        'reports_limit' => $reports_limit,
        'reports_remaining' => $reports_remaining,
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
