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
 * Check export eligibility AJAX endpoint.
 *
 * This endpoint calls the backend API to verify export eligibility.
 * The backend is the single source of truth for export limits - no local
 * limit checking is performed to prevent tampering.
 *
 * Security: Fail closed - if backend is unreachable, exports are denied.
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
$format = required_param('format', PARAM_ALPHA);
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

    // Call backend API to check export eligibility
    // The backend is the ONLY authority for export limits
    $endpoint = rtrim($backend_url, '/') . '/exports/check-eligibility';

    $post_data = json_encode([
        'format' => $format,
    ]);

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

    // Handle connection/timeout errors - FAIL CLOSED
    if ($response === false || !empty($curl_error)) {
        error_log('[Adeptus Insights] Export eligibility check failed - curl error: ' . $curl_error);
        throw new Exception('Unable to verify export eligibility. Please try again later.');
    }

    // Handle HTTP errors - FAIL CLOSED
    if ($http_code !== 200) {
        error_log('[Adeptus Insights] Export eligibility check failed - HTTP ' . $http_code . ': ' . $response);

        if ($http_code === 401) {
            throw new Exception('Authentication failed. Please check your plugin configuration.');
        } else if ($http_code === 403) {
            throw new Exception('Access denied. Your subscription may have expired.');
        } else if ($http_code === 404) {
            throw new Exception('Export verification service unavailable. Please contact support.');
        } else if ($http_code >= 500) {
            throw new Exception('Server error. Please try again later.');
        } else {
            throw new Exception('Unable to verify export eligibility. Please try again later.');
        }
    }

    // Parse backend response
    $backend_data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('[Adeptus Insights] Export eligibility check failed - invalid JSON response');
        throw new Exception('Invalid response from server. Please try again later.');
    }

    if (!isset($backend_data['success'])) {
        error_log('[Adeptus Insights] Export eligibility check failed - missing success field');
        throw new Exception('Invalid response from server. Please try again later.');
    }

    // Return backend response - the backend is authoritative
    echo json_encode([
        'success' => $backend_data['success'],
        'eligible' => $backend_data['eligible'] ?? false,
        'message' => $backend_data['message'] ?? 'Unknown status',
        'reason' => $backend_data['reason'] ?? null,
        'exports_used' => $backend_data['exports_used'] ?? 0,
        'exports_limit' => $backend_data['exports_limit'] ?? 0,
        'exports_remaining' => $backend_data['exports_remaining'] ?? 0,
        'allowed_formats' => $backend_data['allowed_formats'] ?? [],
    ]);
} catch (Exception $e) {
    // FAIL CLOSED - deny export if we cannot verify eligibility with backend
    echo json_encode([
        'success' => false,
        'eligible' => false,
        'message' => $e->getMessage(),
    ]);
}

exit;
