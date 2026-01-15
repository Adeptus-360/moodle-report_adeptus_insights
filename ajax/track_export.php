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
 * Track export usage AJAX endpoint.
 *
 * This endpoint calls the backend API to track export usage.
 * The backend is the single source of truth for export counts.
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
$report_name = required_param('report_name', PARAM_TEXT);
$sesskey = required_param('sesskey', PARAM_ALPHANUM);

// Validate session key
if (!confirm_sesskey($sesskey)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid session key']);
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

    // Call backend API to track export
    // The backend is the ONLY authority for export tracking
    $endpoint = rtrim($backend_url, '/') . '/exports/track';

    $post_data = json_encode([
        'format' => $format,
        'report_name' => $report_name,
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

    // Handle connection errors - log but don't fail the user experience
    if ($response === false || !empty($curl_error)) {
        error_log('[Adeptus Insights] Export tracking failed - curl error: ' . $curl_error);
        // Still return success to not disrupt user, but log the issue
        echo json_encode([
            'success' => true,
            'message' => 'Export completed (tracking pending)',
            'tracking_error' => true,
        ]);
        exit;
    }

    // Handle HTTP errors
    if ($http_code !== 200) {
        error_log('[Adeptus Insights] Export tracking failed - HTTP ' . $http_code . ': ' . $response);
        // Still return success to not disrupt user
        echo json_encode([
            'success' => true,
            'message' => 'Export completed (tracking pending)',
            'tracking_error' => true,
        ]);
        exit;
    }

    // Parse backend response
    $backend_data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('[Adeptus Insights] Export tracking failed - invalid JSON response');
        echo json_encode([
            'success' => true,
            'message' => 'Export completed (tracking pending)',
            'tracking_error' => true,
        ]);
        exit;
    }

    // Return backend response
    echo json_encode([
        'success' => $backend_data['success'] ?? true,
        'message' => $backend_data['message'] ?? 'Export tracked successfully',
        'exports_used' => $backend_data['exports_used'] ?? 0,
        'exports_remaining' => $backend_data['exports_remaining'] ?? 0,
        'exports_limit' => $backend_data['exports_limit'] ?? 0,
    ]);
} catch (Exception $e) {
    error_log('[Adeptus Insights] Export tracking exception: ' . $e->getMessage());
    // Don't fail the user experience for tracking errors
    echo json_encode([
        'success' => true,
        'message' => 'Export completed (tracking pending)',
        'tracking_error' => true,
    ]);
}

exit;
