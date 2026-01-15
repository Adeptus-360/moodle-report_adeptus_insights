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
 * Track report deletion AJAX endpoint.
 *
 * This endpoint calls the backend API to track when a report is deleted.
 * The backend is the single source of truth for report counts.
 * Deleting reports frees up slots within the user's limit.
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
$report_name = required_param('report_name', PARAM_TEXT);
$is_ai_generated = optional_param('is_ai_generated', false, PARAM_BOOL);
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

    // Call backend API to track report deletion
    // The backend is the ONLY authority for report tracking
    $endpoint = rtrim($backend_url, '/') . '/api/v1/report-limits/track-deleted';

    $post_data = json_encode([
        'report_name' => $report_name,
        'is_ai_generated' => $is_ai_generated,
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
    // The report was already deleted, we just couldn't track it
    if ($response === false || !empty($curl_error)) {
        error_log('[Adeptus Insights] Report deletion tracking failed - curl error: ' . $curl_error);
        echo json_encode([
            'success' => true,
            'message' => 'Report deleted (tracking pending)',
            'tracking_error' => true,
        ]);
        exit;
    }

    // Handle HTTP errors - log but don't fail
    if ($http_code !== 200) {
        error_log('[Adeptus Insights] Report deletion tracking failed - HTTP ' . $http_code . ': ' . $response);
        echo json_encode([
            'success' => true,
            'message' => 'Report deleted (tracking pending)',
            'tracking_error' => true,
        ]);
        exit;
    }

    // Parse backend response
    $backend_data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('[Adeptus Insights] Report deletion tracking failed - invalid JSON response');
        echo json_encode([
            'success' => true,
            'message' => 'Report deleted (tracking pending)',
            'tracking_error' => true,
        ]);
        exit;
    }

    // Return backend response - handle alternate field names
    $reports_used = $backend_data['reports_used'] ?? $backend_data['used'] ?? 0;
    $reports_limit = $backend_data['reports_limit'] ?? $backend_data['limit'] ?? 0;
    $reports_remaining = $backend_data['reports_remaining'] ?? $backend_data['remaining'] ?? 0;

    echo json_encode([
        'success' => $backend_data['success'] ?? true,
        'message' => $backend_data['message'] ?? 'Report deletion tracked successfully',
        'reports_used' => $reports_used,
        'reports_limit' => $reports_limit,
        'reports_remaining' => $reports_remaining,
    ]);
} catch (Exception $e) {
    error_log('[Adeptus Insights] Report deletion tracking exception: ' . $e->getMessage());
    // Don't fail the user experience for tracking errors
    echo json_encode([
        'success' => true,
        'message' => 'Report deleted (tracking pending)',
        'tracking_error' => true,
    ]);
}

exit;
