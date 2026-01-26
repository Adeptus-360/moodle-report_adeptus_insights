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
 * Track report creation AJAX endpoint.
 *
 * This endpoint calls the backend API to track when a report is created.
 * The backend is the single source of truth for report counts.
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
$reportname = required_param('report_name', PARAM_TEXT);
$isaigenerated = optional_param('is_ai_generated', false, PARAM_BOOL);
$sesskey = required_param('sesskey', PARAM_ALPHANUM);

// Validate session key
if (!confirm_sesskey($sesskey)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => get_string('error_invalid_sesskey', 'report_adeptus_insights')]);
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

    // Call backend API to track report creation
    // The backend is the ONLY authority for report tracking
    $endpoint = rtrim($backendurl, '/') . '/report-limits/track-created';

    $postdata = json_encode([
        'report_name' => $reportname,
        'is_ai_generated' => $isaigenerated,
    ]);

    $curl = new \curl();
    $curl->setHeader('Content-Type: application/json');
    $curl->setHeader('Accept: application/json');
    $curl->setHeader('X-API-Key: ' . $apikey);
    $options = [
        'CURLOPT_TIMEOUT' => 15,
        'CURLOPT_CONNECTTIMEOUT' => 10,
        'CURLOPT_SSL_VERIFYPEER' => true,
    ];

    $response = $curl->post($endpoint, $postdata, $options);
    $info = $curl->get_info();
    $httpcode = $info['http_code'] ?? 0;
    $curlerror = $curl->get_errno() ? $curl->error : '';

    // Handle connection errors - log but don't fail the user experience
    // The report was already created, we just couldn't track it
    if ($response === false || !empty($curlerror)) {
        debugging('[Adeptus Insights] Report creation tracking failed - curl error: ' . $curlerror, DEBUG_DEVELOPER);
        echo json_encode([
            'success' => true,
            'message' => get_string('report_created_tracking_pending', 'report_adeptus_insights'),
            'tracking_error' => true,
        ]);
        exit;
    }

    // Handle HTTP errors - log but don't fail
    if ($httpcode !== 200) {
        debugging('[Adeptus Insights] Report creation tracking failed - HTTP ' . $httpcode . ': ' . $response, DEBUG_DEVELOPER);
        echo json_encode([
            'success' => true,
            'message' => get_string('report_created_tracking_pending', 'report_adeptus_insights'),
            'tracking_error' => true,
        ]);
        exit;
    }

    // Parse backend response
    $backenddata = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        debugging('[Adeptus Insights] Report creation tracking failed - invalid JSON response', DEBUG_DEVELOPER);
        echo json_encode([
            'success' => true,
            'message' => get_string('report_created_tracking_pending', 'report_adeptus_insights'),
            'tracking_error' => true,
        ]);
        exit;
    }

    // Return backend response - handle alternate field names
    $reportsused = $backenddata['reports_used'] ?? $backenddata['used'] ?? 0;
    $reportslimit = $backenddata['reports_limit'] ?? $backenddata['limit'] ?? 0;
    $reportsremaining = $backenddata['reports_remaining'] ?? $backenddata['remaining'] ?? 0;

    echo json_encode([
        'success' => $backenddata['success'] ?? true,
        'message' => $backenddata['message'] ?? get_string('report_tracked_success', 'report_adeptus_insights'),
        'reports_used' => $reportsused,
        'reports_limit' => $reportslimit,
        'reports_remaining' => $reportsremaining,
    ]);
} catch (Exception $e) {
    debugging('[Adeptus Insights] Report creation tracking exception: ' . $e->getMessage(), DEBUG_DEVELOPER);
    // Don't fail the user experience for tracking errors
    echo json_encode([
        'success' => true,
        'message' => get_string('report_created_tracking_pending', 'report_adeptus_insights'),
        'tracking_error' => true,
    ]);
}

exit;
