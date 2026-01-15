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
require_once(__DIR__ . '/../classes/api_config.php');

// Check for valid login
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

    // Get API key and backend URL
    require_once($CFG->dirroot . '/report/adeptus_insights/classes/installation_manager.php');
    $installationmanager = new \report_adeptus_insights\installation_manager();
    $apikey = $installationmanager->get_api_key();
    $backendapiurl = \report_adeptus_insights\api_config::get_backend_url();

    if (empty($apikey)) {
        echo json_encode([
            'success' => false,
            'message' => 'API key not configured',
        ]);
        exit;
    }

    switch ($action) {
        case 'remove_single':
            $slug = required_param('slug', PARAM_TEXT);

            // Delete the specific wizard report from backend
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $backendapiurl . '/wizard-reports/' . urlencode($slug) . '?user_id=' . $userid);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $apikey,
            ]);

            $response = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpcode === 200) {
                $data = json_decode($response, true);
                echo json_encode(['success' => true, 'message' => $data['message'] ?? 'Report removed successfully']);
            } else {
                $data = json_decode($response, true);
                echo json_encode(['success' => false, 'message' => $data['message'] ?? 'Failed to remove report']);
            }
            break;

        case 'clear_all':
            // Delete all wizard reports for the user from backend
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $backendapiurl . '/wizard-reports');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['user_id' => $userid]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $apikey,
            ]);

            $response = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpcode === 200) {
                $data = json_decode($response, true);
                $count = $data['deleted_count'] ?? 0;
                echo json_encode(['success' => true, 'message' => "Deleted {$count} wizard reports"]);
            } else {
                $data = json_decode($response, true);
                echo json_encode(['success' => false, 'message' => $data['message'] ?? 'Failed to clear reports']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action . '. Expected: clear_all or remove_single']);
            break;
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error managing generated reports: ' . $e->getMessage(),
    ]);
}
