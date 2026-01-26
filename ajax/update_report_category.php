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
 * Update report category AJAX endpoint.
 *
 * This endpoint proxies category update requests to the backend API.
 * It avoids CORS issues by making server-to-server calls.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../classes/installation_manager.php');
require_once(__DIR__ . '/../classes/api_config.php');

// Require login and capability.
require_login();
$context = context_system::instance();
$PAGE->set_context($context);
require_capability('report/adeptus_insights:view', $context);

// Get parameters.
$slug = required_param('slug', PARAM_TEXT);
$categoryid = required_param('category_id', PARAM_INT);
$source = optional_param('source', 'assistant', PARAM_ALPHA);
$sesskey = required_param('sesskey', PARAM_ALPHANUM);

// Validate session key.
if (!confirm_sesskey($sesskey)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => get_string('error_invalid_sesskey', 'report_adeptus_insights')]);
    exit;
}

header('Content-Type: application/json');

try {
    // Get installation manager and API configuration.
    $installationmanager = new \report_adeptus_insights\installation_manager();
    $apikey = $installationmanager->get_api_key();
    $backendurl = \report_adeptus_insights\api_config::get_backend_url();

    if (empty($apikey)) {
        throw new Exception(get_string('error_installation_not_configured', 'report_adeptus_insights'));
    }

    // Determine the correct API endpoint based on report source.
    // Backend uses PUT to /wizard-reports/{slug}/category or /ai-reports/{slug}/category
    if ($source === 'wizard') {
        $endpoint = rtrim($backendurl, '/') . '/wizard-reports/' . urlencode($slug) . '/category';
    } else {
        $endpoint = rtrim($backendurl, '/') . '/ai-reports/' . urlencode($slug) . '/category';
    }

    $postdata = json_encode([
        'category_id' => $categoryid,
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . $apikey,
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlerror = curl_error($ch);
    curl_close($ch);

    // Handle connection errors.
    if ($response === false || !empty($curlerror)) {
        debugging('[Adeptus Insights] Update report category failed - curl error: ' . $curlerror, DEBUG_DEVELOPER);
        echo json_encode([
            'success' => false,
            'message' => get_string('error_connect_backend', 'report_adeptus_insights'),
        ]);
        exit;
    }

    // Handle HTTP errors.
    if ($httpcode !== 200 && $httpcode !== 204) {
        debugging('[Adeptus Insights] Update report category failed - HTTP ' . $httpcode .
            ' Endpoint: ' . $endpoint . ' Response: ' . substr($response, 0, 500), DEBUG_DEVELOPER);
        $errordata = json_decode($response, true);
        $errormessage = $errordata['message'] ?? get_string('category_update_failed', 'report_adeptus_insights');
        echo json_encode([
            'success' => false,
            'message' => $errormessage,
        ]);
        exit;
    }

    // Parse and return backend response.
    $backenddata = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        debugging('[Adeptus Insights] Update report category failed - invalid JSON response', DEBUG_DEVELOPER);
        echo json_encode([
            'success' => false,
            'message' => get_string('error_invalid_backend_json', 'report_adeptus_insights'),
        ]);
        exit;
    }

    echo json_encode([
        'success' => $backenddata['success'] ?? true,
        'message' => $backenddata['message'] ?? get_string('category_updated', 'report_adeptus_insights'),
        'data' => $backenddata['data'] ?? null,
    ]);
} catch (Exception $e) {
    debugging('[Adeptus Insights] Update report category exception: ' . $e->getMessage(), DEBUG_DEVELOPER);
    echo json_encode([
        'success' => false,
        'message' => get_string('error_managing_category', 'report_adeptus_insights', $e->getMessage()),
    ]);
}

exit;
