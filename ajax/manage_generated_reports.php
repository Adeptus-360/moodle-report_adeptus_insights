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
 * Manage generated wizard reports AJAX endpoint.
 *
 * @package     report_adeptus_insights
 * @copyright   2026 Adeptus 360 <info@adeptus360.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Check for valid login.
require_login();

// Check capabilities
$context = context_system::instance();
require_capability('report/adeptus_insights:view', $context);

// Check session key
confirm_sesskey();

// Set JSON response headers
header('Content-Type: application/json');

try {
    $action = required_param('action', PARAM_TEXT);
    $userid = $USER->id;

    // Get API key and backend URL.
    $installationmanager = new \report_adeptus_insights\installation_manager();
    $apikey = $installationmanager->get_api_key();
    $backendapiurl = \report_adeptus_insights\api_config::get_backend_url();

    if (empty($apikey)) {
        echo json_encode([
            'success' => false,
            'message' => get_string('error_api_key_not_configured', 'report_adeptus_insights'),
        ]);
        exit;
    }

    switch ($action) {
        case 'remove_single':
            $slug = required_param('slug', PARAM_TEXT);
            $source = optional_param('source', 'wizard', PARAM_TEXT);

            if ($source === 'assistant') {
                // Delete AI/assistant report from backend using DELETE method
                $curl = new \curl();
                $curl->setHeader('Content-Type: application/json');
                $curl->setHeader('Accept: application/json');
                $curl->setHeader('Authorization: Bearer ' . $apikey);
                $options = [
                    'CURLOPT_TIMEOUT' => 15,
                    'CURLOPT_SSL_VERIFYPEER' => true,
                    'CURLOPT_CUSTOMREQUEST' => 'DELETE',
                ];
                $response = $curl->get($backendapiurl . '/ai-reports/' . urlencode($slug), [], $options);
                $info = $curl->get_info();
                $httpcode = $info['http_code'] ?? 0;
                $curlerror = $curl->get_errno() ? $curl->error : '';

                if ($curlerror) {
                    echo json_encode(['success' => false, 'message' => get_string('error_connection', 'report_adeptus_insights', $curlerror)]);
                } else if ($httpcode === 200 || $httpcode === 204) {
                    $data = json_decode($response, true);
                    echo json_encode(['success' => true, 'message' => $data['message'] ?? get_string('ai_report_delete_success', 'report_adeptus_insights')]);
                } else {
                    $data = json_decode($response, true);
                    echo json_encode(['success' => false, 'message' => $data['message'] ?? get_string('ai_report_delete_failed', 'report_adeptus_insights', $httpcode)]);
                }
            } else {
                // Delete wizard report from backend
                $curl = new \curl();
                $curl->setHeader('Content-Type: application/json');
                $curl->setHeader('Accept: application/json');
                $curl->setHeader('Authorization: Bearer ' . $apikey);
                $options = [
                    'CURLOPT_TIMEOUT' => 10,
                    'CURLOPT_SSL_VERIFYPEER' => true,
                    'CURLOPT_CUSTOMREQUEST' => 'DELETE',
                ];
                $response = $curl->get($backendapiurl . '/wizard-reports/' . urlencode($slug) . '?user_id=' . $userid, [], $options);
                $info = $curl->get_info();
                $httpcode = $info['http_code'] ?? 0;

                if ($httpcode === 200) {
                    $data = json_decode($response, true);
                    echo json_encode(['success' => true, 'message' => $data['message'] ?? get_string('report_removed', 'report_adeptus_insights')]);
                } else {
                    $data = json_decode($response, true);
                    echo json_encode(['success' => false, 'message' => $data['message'] ?? get_string('report_remove_failed', 'report_adeptus_insights')]);
                }
            }
            break;

        case 'clear_all':
            // Delete all wizard reports for the user from backend
            $curl = new \curl();
            $curl->setHeader('Content-Type: application/json');
            $curl->setHeader('Accept: application/json');
            $curl->setHeader('Authorization: Bearer ' . $apikey);
            $options = [
                'CURLOPT_TIMEOUT' => 15,
                'CURLOPT_SSL_VERIFYPEER' => true,
                'CURLOPT_CUSTOMREQUEST' => 'DELETE',
            ];
            $postdata = json_encode(['user_id' => $userid]);
            $response = $curl->post($backendapiurl . '/wizard-reports', $postdata, $options);
            $info = $curl->get_info();
            $httpcode = $info['http_code'] ?? 0;

            if ($httpcode === 200) {
                $data = json_decode($response, true);
                $count = $data['deleted_count'] ?? 0;
                echo json_encode(['success' => true, 'message' => get_string('reports_cleared', 'report_adeptus_insights', $count)]);
            } else {
                $data = json_decode($response, true);
                echo json_encode(['success' => false, 'message' => $data['message'] ?? get_string('error_clear_failed', 'report_adeptus_insights')]);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => get_string('error_invalid_action', 'report_adeptus_insights')]);
            break;
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => get_string('error_managing_reports', 'report_adeptus_insights', $e->getMessage()),
    ]);
}
