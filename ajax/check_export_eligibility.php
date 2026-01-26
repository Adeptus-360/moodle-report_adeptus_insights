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
    echo json_encode(['success' => false, 'eligible' => false, 'message' => get_string('error_invalid_sesskey', 'report_adeptus_insights')]);
    exit;
}

header('Content-Type: application/json');

try {
    // Get installation manager and API configuration
    $installationmanager = new \report_adeptus_insights\installation_manager();
    $apikey = $installationmanager->get_api_key();
    $backendurl = \report_adeptus_insights\api_config::get_backend_url();

    if (empty($apikey)) {
        throw new Exception(get_string('error_installation_not_configured', 'report_adeptus_insights'));
    }

    // Call backend API to check export eligibility
    // The backend is the ONLY authority for export limits
    $endpoint = rtrim($backendurl, '/') . '/exports/check-eligibility';

    $postdata = json_encode([
        'format' => $format,
    ]);

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

    // Handle connection/timeout errors - FAIL CLOSED
    if ($response === false || !empty($curlerror)) {
        debugging('[Adeptus Insights] Export eligibility check failed - curl error: ' . $curlerror, DEBUG_DEVELOPER);
        throw new Exception(get_string('error_verify_eligibility', 'report_adeptus_insights'));
    }

    // Handle HTTP errors - FAIL CLOSED
    if ($httpcode !== 200) {
        debugging('[Adeptus Insights] Export eligibility check failed - HTTP ' . $httpcode . ': ' . $response, DEBUG_DEVELOPER);

        if ($httpcode === 401) {
            throw new Exception(get_string('error_auth_failed', 'report_adeptus_insights'));
        } else if ($httpcode === 403) {
            throw new Exception(get_string('error_access_denied', 'report_adeptus_insights'));
        } else if ($httpcode === 404) {
            throw new Exception(get_string('error_service_unavailable', 'report_adeptus_insights'));
        } else if ($httpcode >= 500) {
            throw new Exception(get_string('error_server', 'report_adeptus_insights'));
        } else {
            throw new Exception(get_string('error_verify_eligibility', 'report_adeptus_insights'));
        }
    }

    // Parse backend response
    $backenddata = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        debugging('[Adeptus Insights] Export eligibility check failed - invalid JSON response', DEBUG_DEVELOPER);
        throw new Exception(get_string('error_invalid_server_response', 'report_adeptus_insights'));
    }

    if (!isset($backenddata['success'])) {
        debugging('[Adeptus Insights] Export eligibility check failed - missing success field', DEBUG_DEVELOPER);
        throw new Exception(get_string('error_invalid_server_response', 'report_adeptus_insights'));
    }

    // Return backend response - the backend is authoritative
    echo json_encode([
        'success' => $backenddata['success'],
        'eligible' => $backenddata['eligible'] ?? false,
        'message' => $backenddata['message'] ?? get_string('unknown_status', 'report_adeptus_insights'),
        'reason' => $backenddata['reason'] ?? null,
        'exports_used' => $backenddata['exports_used'] ?? 0,
        'exports_limit' => $backenddata['exports_limit'] ?? 0,
        'exports_remaining' => $backenddata['exports_remaining'] ?? 0,
        'allowed_formats' => $backenddata['allowed_formats'] ?? [],
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
