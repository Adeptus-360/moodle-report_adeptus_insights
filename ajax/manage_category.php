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
 * Category management AJAX endpoint.
 *
 * This endpoint proxies category management requests to the backend API.
 * It avoids CORS issues by making server-to-server calls.
 *
 * Supported actions: list, create, update, delete
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');

// Require login and capability.
require_login();
$context = context_system::instance();
$PAGE->set_context($context);
require_capability('report/adeptus_insights:view', $context);

// Get parameters.
$action = required_param('action', PARAM_ALPHA);
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

    // Build endpoint and request based on action.
    $endpoint = rtrim($backendurl, '/') . '/reports/categories';
    $method = 'GET';
    $postdata = null;

    switch ($action) {
        case 'list':
            $method = 'GET';
            break;

        case 'create':
            $method = 'POST';
            $name = required_param('name', PARAM_TEXT);
            $color = optional_param('color', '#6c757d', PARAM_TEXT);
            $postdata = json_encode([
                'name' => $name,
                'color' => $color,
            ]);
            break;

        case 'update':
            $method = 'PUT';
            $categoryid = required_param('category_id', PARAM_INT);
            $name = required_param('name', PARAM_TEXT);
            $color = optional_param('color', '#6c757d', PARAM_TEXT);
            $endpoint .= '/' . $categoryid;
            $postdata = json_encode([
                'name' => $name,
                'color' => $color,
            ]);
            break;

        case 'delete':
            $method = 'DELETE';
            $categoryid = required_param('category_id', PARAM_INT);
            $endpoint .= '/' . $categoryid;
            break;

        default:
            throw new Exception(get_string('error_invalid_action', 'report_adeptus_insights'));
    }

    $curl = new \curl();
    $curl->setHeader('Content-Type: application/json');
    $curl->setHeader('Accept: application/json');
    $curl->setHeader('Authorization: Bearer ' . $apikey);
    $options = [
        'CURLOPT_TIMEOUT' => 15,
        'CURLOPT_CONNECTTIMEOUT' => 10,
        'CURLOPT_SSL_VERIFYPEER' => true,
    ];

    // Execute request based on HTTP method.
    switch ($method) {
        case 'GET':
            $response = $curl->get($endpoint, [], $options);
            break;
        case 'POST':
            $response = $curl->post($endpoint, $postdata, $options);
            break;
        case 'PUT':
            $response = $curl->put($endpoint, $postdata, $options);
            break;
        case 'DELETE':
            $response = $curl->delete($endpoint, [], $options);
            break;
        default:
            throw new Exception(get_string('error_invalid_action', 'report_adeptus_insights'));
    }

    $info = $curl->get_info();
    $httpcode = $info['http_code'] ?? 0;
    $curlerror = $curl->get_errno() ? $curl->error : '';

    // Handle connection errors.
    if ($response === false || !empty($curlerror)) {
        debugging('[Adeptus Insights] Category management failed - curl error: ' . $curlerror, DEBUG_DEVELOPER);
        echo json_encode([
            'success' => false,
            'message' => get_string('error_connect_backend', 'report_adeptus_insights'),
        ]);
        exit;
    }

    // Handle HTTP errors.
    if ($httpcode !== 200 && $httpcode !== 201) {
        debugging('[Adeptus Insights] Category management failed - HTTP ' . $httpcode . ': ' . $response, DEBUG_DEVELOPER);
        $errordata = json_decode($response, true);
        echo json_encode([
            'success' => false,
            'message' => $errordata['message'] ?? get_string('category_manage_failed', 'report_adeptus_insights'),
        ]);
        exit;
    }

    // Parse and return backend response.
    $backenddata = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        debugging('[Adeptus Insights] Category management failed - invalid JSON response', DEBUG_DEVELOPER);
        echo json_encode([
            'success' => false,
            'message' => get_string('error_invalid_backend_json', 'report_adeptus_insights'),
        ]);
        exit;
    }

    // Return the backend response as-is for compatibility.
    echo json_encode($backenddata);
} catch (Exception $e) {
    debugging('[Adeptus Insights] Category management exception: ' . $e->getMessage(), DEBUG_DEVELOPER);
    echo json_encode([
        'success' => false,
        'message' => get_string('error_managing_category', 'report_adeptus_insights', $e->getMessage()),
    ]);
}

exit;
